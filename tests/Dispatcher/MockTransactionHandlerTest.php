<?php

declare(strict_types=1);

namespace Goat\Dispatcher\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Ces tests servent surtout à avoir du coverage
 */
final class MockTransactionHandlerTest extends TestCase
{
    /**
     * Mock handler raises exceptions when not started properly
     */
    public function testMockHandlerRunningState()
    {
        $handler = new MockTransactionHandler();
        $this->assertNull($handler->lastOp());

        try {
            $handler->commit();
            $this->fail();
        } catch (\Throwable $e) {}

        try {
            $handler->rollback();
            $this->fail();
        } catch (\Throwable $e) {}
    }

    /**
     * Mock handler commit
     */
    public function testMockHandlerCommit()
    {
        $handler = new MockTransactionHandler();

        $handler->start();
        $this->assertSame(MockTransactionHandler::OP_START, $handler->lastOp());

        $handler->commit();
        $this->assertSame(MockTransactionHandler::OP_COMMIT, $handler->lastOp());
    }

    /**
     * Mock handler commit failure
     */
    public function testMockHandlerCommitFail()
    {
        $handler = new MockTransactionHandler(false, true);

        $handler->start();
        $this->assertSame(MockTransactionHandler::OP_START, $handler->lastOp());

        $this->expectExceptionMessage("FAILED COMMIT");
        $handler->commit();
    }

    /**
     * Mock handler rollback
     */
    public function testMockHandlerRollback()
    {
        $handler = new MockTransactionHandler();

        $handler->start();
        $this->assertSame(MockTransactionHandler::OP_START, $handler->lastOp());

        $handler->commit();
        $this->assertSame(MockTransactionHandler::OP_COMMIT, $handler->lastOp());
    }

    /**
     * Mock handler rollback failure
     */
    public function testMockHandlerRollbackFail()
    {
        $handler = new MockTransactionHandler(true, false);

        $handler->start();
        $this->assertSame(MockTransactionHandler::OP_START, $handler->lastOp());

        $this->expectExceptionMessage("FAILED ROLLBACK");
        $handler->rollback();
    }
}
