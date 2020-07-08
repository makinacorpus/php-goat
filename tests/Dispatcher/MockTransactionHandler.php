<?php

declare(strict_types=1);

namespace Goat\Dispatcher\Tests;

use Goat\Dispatcher\TransactionHandler;

/**
 * Ces tests servent surtout à avoir du coverage
 */
final class MockTransactionHandler implements TransactionHandler
{
    const OP_COMMIT = 'commit';
    const OP_CREATE = 'create';
    const OP_NONE = null;
    const OP_ROLLBACK = 'rollback';
    const OP_START = 'start';

    private $failAtCommit;
    private $failAtRollback;
    private $isRunning = false;
    private $lastOp;

    /**
     * Default constructor
     */
    public function __construct(bool $failAtRollback = false, bool $failAtCommit = false)
    {
        $this->failAtCommit = $failAtCommit;
        $this->failAtRollback = $failAtRollback;
    }

    public function lastOp(): ?string
    {
        return $this->lastOp;
    }

    public function start(): void
    {
        $this->lastOp = self::OP_START;
        $this->isRunning = true;
    }

    /**
     * {@inheritdoc}
     */
    public function commit(): void
    {
        $this->lastOp = self::OP_COMMIT;
        if (!$this->isRunning) {
            throw new \Exception("NOT RUNNING");
        }
        if ($this->failAtCommit) {
            throw new \Exception("FAILED COMMIT");
        }
    }

    /**
     * {@inheritdoc}
     */
    public function rollback(?\Throwable $previous = null): void
    {
        $this->lastOp = self::OP_ROLLBACK;
        if (!$this->isRunning) {
            if ($previous) {
                throw new \Exception("NOT RUNNING", null, $previous);
            } else {
                throw new \Exception("NOT RUNNING");
            }
        }
        if ($this->failAtRollback) {
            if ($previous) {
                throw new \Exception("FAILED ROLLBACK", null, $previous);
            } else {
                throw new \Exception("FAILED ROLLBACK");
            }
        }
    }
}
