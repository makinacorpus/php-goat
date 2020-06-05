<?php

declare(strict_types=1);

namespace Goat\Domain\EventStore\Goat;

use Goat\Domain\EventStore\AbstractEventStore;
use Goat\Domain\EventStore\Event;
use Goat\Domain\EventStore\EventQuery;
use Goat\Query\ExpressionLike;
use Goat\Query\ExpressionRaw;
use Goat\Query\ExpressionRelation;
use Goat\Query\ExpressionValue;
use Goat\Query\Query;
use Goat\Query\SelectQuery;
use Goat\Runner\Runner;
use Goat\Runner\Transaction;

final class GoatEventStore extends AbstractEventStore
{
    private Runner $runner;
    private string $indexTable;
    private string $tablePrefix;
    private string $schema;

    /**
     * Default constructor.
     *
     * @todo
     *   Consider null schema, allowing the path resolution be done by your
     *   RDBMS instead.
     *
     * @codeCoverageIgnore
     *   Code coverage does not take into account data provider run methods.
     */
    public function __construct(Runner $runner, string $schema = 'public')
    {
        parent::__construct();

        $this->indexTable = 'event_index';
        $this->runner = $runner;
        $this->schema = $schema;
        $this->tablePrefix = 'event_';
    }

    /**
     * Get index table relation
     *
     * @internal
     *   For \Goat\Domain\Event\Goat\GoatEventQuery usage only.
     */
    public function getIndexRelation(): ExpressionRelation
    {
        return ExpressionRelation::create($this->indexTable, 'index', $this->schema);
    }

    /**
     * Get namespace-specific relation.
     *
     * @internal
     *   For \Goat\Domain\Event\Goat\GoatEventQuery usage only.
     */
    public function getEventRelation(string $namespace): ExpressionRelation
    {
        return ExpressionRelation::create($this->tablePrefix . $namespace, 'event', $this->schema);
    }

    /**
     * Get runner.
     *
     * @internal
     *   For \Goat\Domain\Event\Goat\GoatEventQuery usage only.
     */
    public function getRunner(): Runner
    {
        return $this->runner;
    }

    /**
     * Hydrate event from raw database row values.
     *
     * @internal
     *   For \Goat\Domain\Event\Goat\GoatEventQuery usage only.
     */
    public function hydrateEvent(array $row): Event
    {
        $properties = (array)$row['properties'];
        $message = function () use ($row, $properties) {
            return $this->hydrateMessage(
                $row['aggregate_type'] ?? null,
                $row['aggregate_id'] ?? null,
                $row['name'], $properties, $row['data']
            );
        };

        $callback = \Closure::bind(static function () use ($row, $message, $properties): Event {
            $ret = Event::create($message);

            $ret->aggregateId = $row['aggregate_id'];
            $ret->aggregateRoot = $row['aggregate_root'];
            $ret->aggregateType = $row['aggregate_type'];
            $ret->createdAt = $row['created_at'];
            $ret->errorCode = $row['error_code'];
            $ret->errorMessage = $row['error_message'];
            $ret->errorTrace = $row['error_trace'];
            $ret->hasFailed = $row['has_failed'];
            $ret->properties = $properties;
            $ret->name = $row['name'];
            $ret->namespace = $row['namespace'];
            $ret->position = $row['position'];
            $ret->revision = $row['revision'];

            return $ret;

        }, null, Event::class);

        return $callback();
    }

    /**
     * Store event and return the updated instance (clone of the previous).
     *
     * This whole method could be speed up by writing a nice stored procedure
     * for it, it would fit right here. We have 2 queries at best, 3 at worst.
     */
    protected function doStore(Event $event): Event
    {
        if ($event->isStored()) {
            throw new \BadMethodCallException('You cannot store an already stored event');
        }

        $aggregateId = $event->getAggregateId();
        $aggregateType = $event->getAggregateType();
        $createdAt = $event->createdAt();

        $eventRelation = $this->getEventRelation($namespace = $this->getNamespace($aggregateType));
        $indexRelation = $this->getIndexRelation();

        $builder = $this->runner->getQueryBuilder();
        $transaction = null;

        try {
            // REPEATABLE READ transaction level turns out to be extra-isolated
            // in PostgreSQL, whereas it is level 3 of transaction isolation,
            // it is the legacy implementation of level 4 SERIALIZABLE, which
            // is today even more precautionous with data.
            //
            // The only real risk we have here, is to end up with more than one
            // transactions at once inserting revisions for the same business
            // object, in a scenario were you would have multiple bus consumers
            // in parallel for example.
            //
            // To avoid crashes, the only viable solution we have is:
            //
            //   1. SELECT ... FOR UPDATE to really be sure at least one
            //      transaction will force the others to wait for the other
            //      until it finishes,
            //
            //   2. implement a retry algorithm at the dispatcher level to
            //      replay the failed transaction.
            //
            // Considering this, any malfunction leading to incoherent data is
            // is impossible in this scenario at the application level.

            $transaction = $this
                ->runner
                ->beginTransaction(Transaction::REPEATABLE_READ)
            ;

            $exists = (bool)$builder
                ->select($eventRelation)
                ->columnExpression('true')
                ->where('aggregate_id', $aggregateId)
                ->forUpdate()
                ->execute()
                ->fetchField()
            ;

            $revisionQuery = $builder
                ->select($eventRelation)
                ->columnExpression('COALESCE(max(revision) + 1, 1)')
                ->where('aggregate_id', $aggregateId)
            ;

            // Ensure item exists in database, any revision identifier that
            // goes over 1 tells us that the item already exists, since there
            // are database constraints that enforces it.
            if (!$exists) {
                $builder
                    ->merge($indexRelation)
                    ->setKey(['aggregate_id'])
                    ->onConflictIgnore()
                    ->values([
                        'aggregate_id' => $aggregateId,
                        'aggregate_root' => $event->getAggregateRoot(),
                        'aggregate_type' => $aggregateType,
                        'created_at' => $createdAt,
                        'namespace' => $namespace,
                    ])
                    ->execute()
                ;
            }

            $return = $builder
                ->insert($eventRelation)
                ->values([
                    'aggregate_id' => $aggregateId,
                    'created_at' => $createdAt,
                    'data' => $this->messageToString($event),
                    'error_code' => $event->getErrorCode(),
                    'error_message' => $event->getErrorMessage(),
                    'error_trace' => $event->getErrorTrace(),
                    'has_failed' => $event->hasFailed(),
                    'name' => $event->getName(),
                    'properties' => ExpressionValue::create($event->getProperties(), 'jsonb'),
                    'revision' => $revisionQuery,
                ])
                ->returning('position')
                ->returning('revision')
                ->execute()
                ->fetch()
            ;

            $position = $return['position'];
            $revision = $return['revision'];

            $transaction->commit();

            $func = \Closure::bind(static function (Event $event) use ($position, $revision): Event {
                $ret = clone $event;
                $ret->position = $position;
                $ret->revision = $revision;
                return $ret;
            }, null, Event::class);

            $newEvent = $func($event);

            return $newEvent;

        } catch (\Throwable $e) {
            if ($transaction && $transaction->isStarted()) {
                $transaction->rollback();
            }

            throw $e;
        }
    }

    /**
     * Real update implementation.
     */
    protected function doUpdate(Event $event): Event
    {
        $aggregateType = $event->getAggregateType();
        $eventRelation = $this->getEventRelation($this->getNamespace($aggregateType));

        $this
            ->runner
            ->getQueryBuilder()
            ->update($eventRelation)
            ->sets([
                'error_code' => $event->getErrorCode(),
                'error_message' => $event->getErrorMessage(),
                'error_trace' => $event->getErrorTrace(),
                'has_failed' => $event->hasFailed(),
                'properties' => ExpressionValue::create($event->getProperties(), 'jsonb'),
            ])
            ->where('position', $event->getPosition())
            ->perform()
        ;

        return $event;
    }

    /**
     * Create select query from event query
     *
     * @internal
     *   For \Goat\Domain\Event\Goat\GoatEventQuery usage only.
     */
    public function createSelectQuery(GoatEventQuery $query): SelectQuery
    {
        $nameMap = $this->getNameMap();

        $callback = \Closure::bind(function (GoatEventStore $eventStore) use ($nameMap) {

            $select = $eventStore
                ->getRunner()
                ->getQueryBuilder()
                ->select($eventStore->getEventRelation('default')) // @todo
                ->join($eventStore->getIndexRelation(), 'event.aggregate_id = index.aggregate_id')
            ;

            $where = $select->getWhere();

            if ($this->names) {
                $conditions = [];
                foreach ($this->names as $name) {
                    if ($name !== ($value = $nameMap->getName($name))) {
                        $conditions[] = $value;
                        $conditions[] = $name;
                    } else if ($name !== ($value = $nameMap->getType($name))) {
                        $conditions[] = $value;
                        $conditions[] = $name;
                    } else {
                        $conditions[] = $name;
                    }
                }
                $where->isIn('event.name', $conditions);
            }
            if ($this->searchName) {
                $where->expression(ExpressionLike::iLike('event.name', '%?%', $this->searchName));
            }
            if ($this->searchData) {
                // TODO: use jsonb storage and search ?
                $where->expression(ExpressionLike::iLike('data', '%?%', $this->searchData));
            }
            if ($this->aggregateTypes) {
                $where->isIn('index.aggregate_type', $this->aggregateTypes);
            }
            if ($this->aggregateId) {
                if ($this->aggregateAsRoot) {
                    $where->or()->isEqual('index.aggregate_id', $this->aggregateId)->isEqual('index.aggregate_root', $this->aggregateId);
                } else {
                    $where->isEqual('index.aggregate_id', $this->aggregateId);
                }
            }
            if ($this->dateLowerBound && $this->dateHigherBound) {
                $where->condition(
                    'event.created_at',
                    // need to accept 2019-04-25 10:12:13.22115 as valid for higerBound 2019-04-25 10:12:13
                    // using a date_trunc(second) on event_created_at would be a perf killer, better to check against
                    // 2019-04-25 10:12:14 (note that would also accept 2019-04-25 10:12:14.00000)
                    new ExpressionRaw(\sprintf("'%s'::timestamp without time zone AND '%s'::timestamp without time zone + interval '1 second'",
                        $this->dateLowerBound->format("Y-m-d H:i:s"),
                        $this->dateHigherBound->format("Y-m-d H:i:s")
                    )),
                    'BETWEEN'
                );
            }
            if (null !== $this->failed) {
                $where->condition('event.has_failed', $this->failed);
            }

            if ($this->reverse) {
                // We sometime have bugs with date, only the serial can really
                // properly sort the stream.
                $select->orderBy('event.position', Query::ORDER_DESC);
                $select->orderBy('event.created_at', Query::ORDER_DESC);

                if ($this->position) {
                    $where->isLessOrEqual('event.position', $this->position);
                }
                if ($this->revision) {
                    $where->isLessOrEqual('event.revision', $this->revision);
                }

            } else {
                // Cf. upper note.
                $select->orderBy('event.position', Query::ORDER_ASC);
                $select->orderBy('event.created_at', Query::ORDER_ASC);

                if ($this->position) {
                    $where->isGreaterOrEqual('event.position', $this->position);
                }
                if ($this->revision) {
                    $where->isGreaterOrEqual('event.revision', $this->revision);
                }
            }

            if ($this->dateLowerBound && !$this->dateHigherBound) {
                $where->isGreaterOrEqual('event.created_at', $this->dateLowerBound);
            }

            if ($this->dateHigherBound && !$this->dateLowerBound) {
                $where->isLessorEqual(
                    'event.created_at',
                    new ExpressionRaw(\sprintf("'%s'::timestamp without time zone + interval '1 second'", $this->dateHigherBound->format("Y-m-d H:i:s")))
                );
            }

            return $select;

        }, $query, GoatEventQuery::class);

        return $callback($this);
    }

    /**
     * {@inheritdoc}
     */
    public function query(): EventQuery
    {
        return new GoatEventQuery($this);
    }

    /**
     * {@inheritdoc}
     */
    public function count(EventQuery $query): ?int
    {
        if (!$query instanceof GoatEventQuery) {
            throw new \InvalidArgumentException(
                \sprintf(
                    "Query must be a %s instance, %s given",
                    GoatEventQuery::class, \get_class($query)
                )
            );
        }

        return $this
            ->createSelectQuery($query)
            ->removeAllColumns()
            ->removeAllOrder()
            ->columnExpression('count(event.position)', 'total')
            ->execute()
            ->fetchField()
        ;
    }
}
