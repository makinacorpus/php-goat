<?php

declare(strict_types=1);

namespace Goat\Bridge\Symfony\DataCollector;

use Goat\Query\QueryBuilder;
use Goat\Query\Statement;
use Goat\Runner\EmptyResultIterator;
use Goat\Runner\ResultIterator;
use Goat\Runner\Runner;
use Goat\Runner\Transaction;
use Goat\Runner\Driver\AbstractRunner;

final class RunnerProfiler implements Runner
{
    private $runner;
    private $data = [];

    /**
     * Default constructor
     */
    public function __construct(Runner $runner)
    {
        $this->runner = $runner;
        $this->data = [
            'exception' => 0,
            'execute_count' => 0,
            'execute_time' => 0,
            'perform_count' => 0,
            'perform_time' => 0,
            'prepare_count' => 0,
            'query_count' => 0,
            'query_time' => 0,
            'total_count' => 0,
            'total_time' => 0,
            'transaction_commit_count' => 0,
            'transaction_count' => 0,
            'transaction_rollback_count' => 0,
            'transaction_time' => 0,
            'queries' => [],
        ];
    }

    /**
     * Get collected data so far
     */
    public function getCollectedData(): array
    {
        return $this->data;
    }

    /**
     * Append value to a counter or timer
     */
    public function addTo(string $name, int $value = 1)
    {
        if (!isset($this->data[$name])) {
            $this->data[$name] = $value;
        } else {
            $this->data[$name] += $value;
        }
    }

    /**
     * Log a single query
     */
    private function addQueryToData($query, $arguments = null, $options = null, bool $prepared = false)
    {
        if ($query instanceof Statement && $this->runner instanceof AbstractRunner) {
            $rawSQL = $this->runner->getFormatter()->format($query);
        } else {
            $rawSQL = (string)$query;
        }

        $this->data['queries'][] = [
            'sql' => $rawSQL,
            'params' => $arguments,
            'options' => $options,
            'prepared' => $prepared,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function execute($query, $arguments = null, $options = null): ResultIterator
    {
        $timer = new Timer();
        $this->data['query_count']++;
        $this->data['total_count']++;
        $ret = null;

        try {
            $this->addQueryToData($query, $arguments, $options, false);
            $ret = $this->runner->execute($query, $arguments, $options);
        } catch (\Exception $e) {
            $this->data['exception']++;
            throw $e;
        } finally {
            $duration = $timer->stop();
            // Ignore empty result iterator, this means it fallbacked on perform()
            if ($ret instanceof EmptyResultIterator) {
                $this->data['query_count']--;
                $this->data['total_count']--;
            } else {
                $this->data['query_time'] += $duration;
                $this->data['total_time'] += $duration;
            }
        }

        return $ret;
    }

    /**
     * {@inheritdoc}
     */
    public function perform($query, $arguments = null, $options = null): int
    {
        $timer = new Timer();
        $this->data['perform_count']++;
        $this->data['total_count']++;

        try {
            $this->addQueryToData($query, $arguments, $options, false);
            $ret = $this->runner->perform($query, $arguments, $options);
        } catch (\Exception $e) {
            $this->data['exception']++;
            throw $e;
        } finally {
            $duration = $timer->stop();
            $this->data['perform_time'] += $duration;
            $this->data['total_time'] += $duration;
        }

        return $ret;
    }

    /**
     * {@inheritdoc}
     */
    public function prepareQuery($query, ?string $identifier = null) : string
    {
        $this->data['prepare_count']++;

        $this->addQueryToData($query, null, null, true);

        return $this->runner->prepareQuery($query, $identifier);
    }

    /**
     * {@inheritdoc}
     */
    public function executePreparedQuery(string $identifier, $arguments = null, $options = null) : ResultIterator
    {
        $timer = new Timer();
        $this->data['execute_count']++;
        $this->data['total_count']++;

        try {
            $ret = $this->runner->executePreparedQuery($identifier, $arguments, $options);
        } catch (\Exception $e) {
            $this->data['exception']++;
            throw $e;
        } finally {
            $duration = $timer->stop();
            $this->data['execute_time'] += $duration;
            $this->data['total_time'] += $duration;
        }

        return $ret;
    }

    /**
     * {@inheritdoc}
     */
    public function startTransaction(int $isolationLevel = Transaction::REPEATABLE_READ, bool $allowPending = false): Transaction
    {
        $this->data['transaction_count']++;

        $timer = new Timer();
        $transaction = $this->runner->startTransaction($isolationLevel, $allowPending);

        return new TransactionProfiler($this, $transaction, $timer);
    }

    /**
     * {@inheritdoc}
     */
    public function runTransaction(callable $callback, int $isolationLevel = Transaction::REPEATABLE_READ)
    {
        $ret = null;
        $transaction = $this->startTransaction($isolationLevel, true);

        try {
            if (!$transaction->isStarted()) {
                $transaction->start();
            }
            $ret =\call_user_func($callback, $this->getQueryBuilder(), $transaction, $this);
            $transaction->commit();

        } catch (\Throwable $e) {
            if ($transaction->isStarted()) {
                $transaction->rollback();
            }

            throw $e;
        }

        return $ret;
    }

    /**
     * {@inheritdoc}
     */
    public function getQueryBuilder(): QueryBuilder
    {
        return new QueryBuilderProfiler($this->runner->getQueryBuilder(), $this);
    }

    /**
     * {@inheritdoc}
     */
    public function getDriverName(): string
    {
        return $this->runner->getDriverName();
    }

    /**
     * {@inheritdoc}
     */
    public function supportsDeferingConstraints(): bool
    {
        return $this->runner->supportsDeferingConstraints();
    }

    /**
     * {@inheritdoc}
     */
    public function isTransactionPending(): bool
    {
        return $this->runner->isTransactionPending();
    }

    /**
     * {@inheritdoc}
     */
    public function supportsReturning(): bool
    {
        return $this->runner->supportsReturning();
    }
}
