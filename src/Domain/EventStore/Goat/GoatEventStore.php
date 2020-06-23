<?php

declare(strict_types=1);

namespace Goat\Domain\EventStore\Goat;

use Goat\Domain\EventStore\AbstractEventStore;
use Goat\Domain\EventStore\Event;
use Goat\Domain\EventStore\EventQuery;
use Goat\Query\ExpressionRelation;
use Goat\Query\ExpressionValue;
use Goat\Runner\Runner;
use Goat\Runner\Transaction;
use Ramsey\Uuid\UuidInterface;

final class GoatEventStore extends AbstractEventStore
{
    private Runner $runner;
    private string $indexTable;
    private string $tablePrefix;
    private ?string $schema = null;

    /**
     * Default constructor.
     *
     * @param ?string $schema
     *   Set null to let your RDMS schema resolution do the job or if your
     *   RDMS does not support schema.
     *
     * @codeCoverageIgnore
     *   Code coverage does not take into account data provider run methods.
     */
    public function __construct(Runner $runner, ?string $schema = 'public')
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
            $ret->validAt = $row['valid_at'];

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
        $validAt = $event->validAt();

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
                    'valid_at' => $validAt,
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
                'name' => $event->getName(),
                'properties' => ExpressionValue::create($event->getProperties(), 'jsonb'),
                'valid_at' => $event->validAt(),
            ])
            ->where('position', $event->getPosition())
            ->perform()
        ;

        return $event;
    }

    /**
     * {@inheritdoc}
     */
    protected function doMoveAt(Event $event, int $newRevision): Event
    {
        // @todo later on, attempt to fix revision numbers if possible.
        return $this->doUpdate($event);
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
    public function findByPosition(int $position): Event
    {
        $eventRelation = $this->getEventRelation('default'); // @todo
        $indexRelation = $this->getIndexRelation();

        $event = $this
            ->runner
            ->getQueryBuilder()
            ->select($eventRelation)
            ->columnExpression("event.*")
            ->columns(['index.aggregate_type', 'index.aggregate_root', 'index.namespace'])
            ->join($indexRelation, 'event.aggregate_id = index.aggregate_id')
            ->where('position', $position)
            ->range(1)
            ->execute()
            ->setHydrator(fn ($row) => $this->hydrateEvent($row))
            ->fetch()
        ;

        if (!$event) {
            throw new \InvalidArgumentException(\sprintf("Event with position #%d does not exist", $position));
        }

        return $event;
    }

    /**
     * {@inheritdoc}
     */
    public function findByRevision(UuidInterface $aggregateId, int $revision): Event
    {
        $eventRelation = $this->getEventRelation('default'); // @todo
        $indexRelation = $this->getIndexRelation();

        $event = $this
            ->runner
            ->getQueryBuilder()
            ->select($eventRelation)
            ->columnExpression("event.*")
            ->columns(['index.aggregate_type', 'index.aggregate_root', 'index.namespace'])
            ->join($indexRelation, 'event.aggregate_id = index.aggregate_id')
            ->where('aggregate_id', $aggregateId)
            ->where('revision', $revision)
            ->range(1)
            ->execute()
            ->setHydrator(fn ($row) => $this->hydrateEvent($row))
            ->fetch()
        ;

        if (!$event) {
            throw new \InvalidArgumentException(\sprintf("Event %s#%d does not exist", $aggregateId->toString(), $revision));
        }

        return $event;
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

        return $query
            ->createSelectQuery($query)
            ->removeAllColumns()
            ->removeAllOrder()
            ->columnExpression('count(event.position)', 'total')
            ->execute()
            ->fetchField()
        ;
    }
}
