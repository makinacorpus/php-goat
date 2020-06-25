<?php

declare(strict_types=1);

namespace Goat\Domain\Projector\State;

use Goat\Domain\EventStore\Event;
use Goat\Query\ExpressionRaw;
use Goat\Query\ExpressionRelation;
use Goat\Query\Query;
use Goat\Query\QueryBuilder;
use Goat\Query\SelectQuery;
use Goat\Runner\Runner;

/**
 * goat-query/pgsql implementation.
 */
final class GoatStateStore implements StateStore
{
    private Runner $runner;
    private string $schema;

    public function __construct(Runner $runner, string $schema = 'public')
    {
        $this->runner = $runner;
        $this->schema = $schema;
    }

    /**
     * {@inheritdoc}
     */
    public function lock(string $id): State
    {
        return $this->runner->runTransaction(function (QueryBuilder $builder) use ($id): State {
            $table = $this->table();

            $isLocked = $builder
                ->select($table)
                ->column('is_locked')
                ->condition('id', $id)
                ->forUpdate()
                ->execute()
                ->fetchField()
            ;

            if ($isLocked) {
                throw new ProjectorLockedError($id);
            }

            $query = $builder
                ->merge($table)
                ->onConflictUpdate()
                ->setKey(['id'])
                ->values([
                    'id' => $id,
                    'updated_at' => ExpressionRaw::create('current_timestamp'),
                    'is_locked' => true,
                    // 'is_error' bool NOT NULL DEFAULT false,
                    // 'error_code' bigint NOT NULL DEFAULT 0,
                    // 'error_message' text DEFAULT null,
                    // 'error_trace' 'ext DEFAULT null,
                ])
            ;

            return $this->columns($query)->execute()->fetch();
        });
    }

    /**
     * {@inheritdoc}
     */
    public function unlock(string $id): State
    {
        $query = $this
            ->runner
            ->getQueryBuilder()
            ->merge($this->table())
            ->onConflictUpdate()
            ->setKey(['id'])
            ->values([
                'id' => $id,
                'updated_at' => ExpressionRaw::create('current_timestamp'),
                'is_locked' => false,
                'is_error' => false,
                'error_code' => 0,
                'error_message' => null,
                'error_trace' => null,
            ])
        ;

        return $this->columns($query)->execute()->fetch();
    }

    /**
     * {@inheritdoc}
     */
    public function update(string $id, Event $event, bool $unlock = true): State
    {
        $values = [
            'id' => $id,
            'updated_at' => ExpressionRaw::create('current_timestamp'),
            'last_position' => $event->getPosition(),
            'last_valid_at' => $event->validAt(),
            'is_error' => false,
            'error_code' => 0,
            'error_message' => null,
            'error_trace' => null,
        ];

        if ($unlock) {
            $values['is_locked'] = false;
        }

        $query = $this
            ->runner
            ->getQueryBuilder()
            ->merge($this->table())
            ->onConflictUpdate()
            ->setKey(['id'])
            ->values($values)
        ;

        return $this->columns($query)->execute()->fetch();
    }

    /**
     * {@inheritdoc}
     */
    public function error(string $id, Event $event, string $message, int $errorCode = 0, bool $unlock = true): State
    {
        $values = [
            'id' => $id,
            'updated_at' => ExpressionRaw::create('current_timestamp'),
            'last_position' => $event->getPosition(),
            'last_valid_at' => $event->validAt(),
            'is_error' => true,
            'error_code' => $errorCode,
            'error_message' => $message,
            'error_trace' => null,
        ];

        if ($unlock) {
            $values['is_locked'] = false;
        }

        $query = $this
            ->runner
            ->getQueryBuilder()
            ->merge($this->table())
            ->onConflictUpdate()
            ->setKey(['id'])
            ->values($values)
        ;

        return $this->columns($query)->execute()->fetch();
    }

    /**
     * {@inheritdoc}
     */
    public function exception(string $id, Event $event, \Throwable $exception, bool $unlock = true): State
    {
        $values = [
            'id' => $id,
            'updated_at' => ExpressionRaw::create('current_timestamp'),
            'last_position' => $event->getPosition(),
            'last_valid_at' => $event->validAt(),
            'is_error' => true,
            'error_code' => $exception->getCode(),
            'error_message' => $exception->getMessage(),
            'error_trace' => ArrayStateStore::normalizeExceptionTrace($exception),
        ];

        if ($unlock) {
            $values['is_locked'] = false;
        }

        $query = $this
            ->runner
            ->getQueryBuilder()
            ->merge($this->table())
            ->onConflictUpdate()
            ->setKey(['id'])
            ->values($values)
        ;

        return $this->columns($query)->execute()->fetch();
    }

    /**
     * {@inheritdoc}
     */
    public function latest(string $id): ?State
    {
        return $this
            ->columns(
                $this
                    ->runner
                    ->getQueryBuilder()
                    ->select($this->table())
                    ->condition('id', $id)
            )
            ->execute()
            ->fetch()
        ;
    }

    private function columns(Query $query): Query
    {
        $aliases = [
            'id' => 'id',
            'created_at' => 'createdAt',
            'updated_at' => 'updatedAt',
            'last_position' => 'position',
            'last_valid_at' => 'date',
            'is_locked' => 'isLocked',
            'is_error' => 'isError',
            'error_code' => 'errorCode',
            'error_message' => 'errorMessage',
            'error_trace' => 'errorTrace',
        ];

        if ($query instanceof SelectQuery) {
            foreach ($aliases as $column => $alias) {
                $query->column($column, $alias);
            }
        } else {
            foreach ($aliases as $column => $alias) {
                $query->returning($column, $alias);
            }
        }

        return $query->setOption('class', State::class);
    }

    private function table(): ExpressionRelation
    {
        return ExpressionRelation::create('projector_state', $this->schema);
    }
}
