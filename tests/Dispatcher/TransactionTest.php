<?php

declare(strict_types=1);

namespace Goat\Dispatcher\Tests;

use Goat\Dispatcher\DispatcherTransaction;
use PHPUnit\Framework\TestCase;

/**
 * Ces tests servent surtout à avoir du coverage
 */
final class TransactionTest extends TestCase
{
    /**
     * Rollback should rollback all
     */
    public function testStartsAll()
    {
        $handlers = [
            new MockTransactionHandler(),
            new MockTransactionHandler(),
            new MockTransactionHandler(),
        ];

        $transaction = new DispatcherTransaction($handlers);
        $this->assertTrue($transaction->isRunning());

        foreach ($handlers as $handler) {
            $this->assertSame(MockTransactionHandler::OP_START, $handler->lastOp());
        }
    }

    /**
     * When a single commit fail, everything fails
     */
    public function testFailCommitRaiseErrors()
    {
        $handlers = [
            new MockTransactionHandler(false, false),
            new MockTransactionHandler(false, true),
            new MockTransactionHandler(false, false),
        ];

        $transaction = new DispatcherTransaction($handlers);

        $this->expectExceptionMessage("FAILED COMMIT");
        $transaction->commit();
    }

    /**
     * On rollback failure, exception are thrown, but all transaction handlers have been rollbacked
     */
    public function testFailedRollbackRethrowsExceptions()
    {
        $handlers = [
            new MockTransactionHandler(false, false),
            new MockTransactionHandler(true, false),
            new MockTransactionHandler(false, false),
        ];

        $transaction = new DispatcherTransaction($handlers);

        try {
            $transaction->rollback();
            $this->fail();
        } catch (\Throwable $e) {}

        // All handlers have been correctly rollbacked no matter what 
        foreach ($handlers as $handler) {
            $this->assertSame(MockTransactionHandler::OP_ROLLBACK, $handler->lastOp());
        }
    }

    /**
     * Rollback should rollback all
     */
    public function testRollbackRollbacksAll()
    {
        $handlers = [
            new MockTransactionHandler(),
            new MockTransactionHandler(),
            new MockTransactionHandler(),
        ];

        $transaction = new DispatcherTransaction($handlers);
        $transaction->rollback();

        foreach ($handlers as $handler) {
            $this->assertSame(MockTransactionHandler::OP_ROLLBACK, $handler->lastOp());
        }
    }

    /**
     * Commit shoud commit all
     */
    public function testCommitCommitsAll()
    {
        $handlers = [
            new MockTransactionHandler(),
            new MockTransactionHandler(),
            new MockTransactionHandler(),
        ];

        $transaction = new DispatcherTransaction($handlers);
        $transaction->commit();

        foreach ($handlers as $handler) {
            $this->assertSame(MockTransactionHandler::OP_COMMIT, $handler->lastOp());
        }
    }
}
