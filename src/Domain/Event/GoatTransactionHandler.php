<?php

declare(strict_types=1);

namespace Goat\Domain\Event;

use Goat\Runner\Runner;
use Goat\Runner\Transaction as GoatTransaction;

class GoatTransactionHandler implements TransactionHandler
{
    private $runner;

    /**
     * @var ?\Goat\Runner\Transaction
     */
    private $transaction;

    /**
     * Default constructor
     */
    public function __construct(Runner $runner)
    {
        $this->runner = $runner;
    }

    /**
     * Check for transaction existence
     */
    private function getTransaction(): GoatTransaction
    {
        if (!$this->transaction || !$this->transaction->isStarted()) {
            throw new \BadMethodCallException("No transaction was started");
        }

        return $this->transaction;
    }

    /**
     * {@inheritdoc}
     */
    public function commit(): void
    {
        $this->getTransaction()->commit();
    }

    /**
     * {@inheritdoc}
     */
    public function rollback(?\Throwable $previous = null): void
    {
        $this->getTransaction()->rollback();
    }

    /**
     * {@inheritdoc}
     */
    public function start(): void
    {
        $this->transaction = $this->runner->beginTransaction();
    }
}
