<?php

declare(strict_types=1);

namespace Goat\Domain\Event;

use Goat\Domain\DebuggableTrait;
use Goat\Domain\EventStore\Event;
use Goat\Domain\EventStore\EventStore;
use Goat\Domain\Service\LockService;
use Psr\Log\NullLogger;

abstract class AbstractDispatcher implements Dispatcher
{
    use DebuggableTrait;

    /** @var int */
    private static $commandCount = 0;

    /** @var null|EventStore */
    private $eventStore;

    /** @var null|DispatcherTransaction */
    private $transaction;

    /** @var TransactionHandler[] */
    private $transactionHandlers = [];

    /** @var Projector[] */
    private $projectors = [];

    /** @var bool */
    private $transactionHandlersSet = false;

    /** @var null|LockService */
    private $lockService;

    public function __construct()
    {
        $this->logger = new NullLogger();
    }

    /**
     * {@inheritdoc}
     */
    final public function setLockService(LockService $lockService): void
    {
        $this->lockService = $lockService;
    }

    /**
     * {@inheritdoc}
     */
    final public function setTransactionHandlers(iterable $transactionHandlers): void
    {
        if ($this->transactionHandlersSet) {
            throw new \BadMethodCallException("Transactions handlers are already set");
        }
        $this->transactionHandlers = $transactionHandlers;
    }

    /**
     * {@inheritdoc}
     */
    final public function setProjectors(iterable $projectors): void
    {
        $this->projectors = $projectors;
    }

    /**
     * {@inheritdoc}
     */
    public function setEventStore(EventStore $eventStore): void
    {
        $this->eventStore = $eventStore;
    }

    /**
     * Is there a transaction running?
     */
    final protected function isTransactionRunning(): bool
    {
        return $this->transaction && $this->transaction->isRunning();
    }

    /**
     * Run a new transaction or return the active one.
     */
    final protected function startTransaction(): Transaction
    {
        if ($this->isTransactionRunning()) {
            $this->logger->debug("Dispatcher transaction SKIP already running");

            return $this->transaction;
        }

        $this->logger->debug("Dispatcher transaction START");

        return $this->transaction = new DispatcherTransaction($this->transactionHandlers);
    }

    /**
     * Process
     */
    abstract protected function doSynchronousProcess(MessageEnvelope $envelope): Event;

    /**
     * Handle Projectors
     */
    private function handleProjectors(Event $event): void
    {
        foreach ($this->projectors as $projector) {
            try {
                $this->logger->debug("Projector {projector} BEGIN PROCESS message", ['projector' => $projector::class, 'message' => $event->getMessage()]);
                $projector->onEvent($event);
                $this->logger->debug("Projector {projector} END PROCESS message", ['projector' => $projector::class, 'message' => $event->getMessage()]);
            } catch (\Throwable $e) {
                $this->logger->error("Projector {projector} FAIL", ['projector' => $projector::class, 'exception' => $e]);
            }
        }

    }

    /**
     * Send in bus
     */
    abstract protected function doAsynchronousCommandDispatch(MessageEnvelope $envelope): void;

    /**
     * Send in bus
     */
    abstract protected function doAsynchronousEventDispatch(MessageEnvelope $envelope): void;

    /**
     * Process message with a semaphore
     */
    private function processWithLock(MessageEnvelope $envelope, int $lockId)
    {
        $acquired = false;
        try {
            $this->lockService->getLockOrDie($lockId, \get_class($envelope->getMessage()));
            $acquired = true;
            return $this->doSynchronousProcess($envelope);
        } finally {
            if ($acquired) {
                $this->lockService->release($lockId);
            }
        }
    }

    /**
     * Call doSynchronousProcess but checks for Parallel/Lock potential problems before
     */
    private function synchronousProcess(MessageEnvelope $envelope): void
    {
        if ($this->lockService) {
            $message = $envelope->getMessage();
            if ($message instanceof UnparallelizableMessage) {
                $this->processWithLock($envelope, $message->getUniqueIntIdentifier());
                return;
            }
        }
        $this->doSynchronousProcess($envelope);
    }

    /**
     * Really store event callback
     *
     * DO NOT CALL THIS METHOD, use storeEvent() or storeEventWithError() instead.
     */
    private function doStoreEventWith(MessageEnvelope $envelope, bool $success = true, array $extra = []): void
    {
        if ($this->eventStore) {
            $id = $type = null;
            if (($message = $envelope->getMessage()) instanceof Message) {
                $id = $message->getAggregateId();
                $type = $message->getAggregateType();
            }

            $event = $this->eventStore->store($message, $id, $type, !$success, ['properties' => $envelope->getProperties()] + $extra);
            $this->handleProjectors($event);
        }
    }

    /**
     * Normalize exception trace
     */
    private function normalizeExceptionTrace(\Throwable $exception)
    {
        $output = '';
        do {
            if ($output) {
                $output .= "\n";
            }
            $output .= \sprintf("%s: %s\n", \get_class($exception), $exception->getMessage());
            $output .= $exception->getTraceAsString();
        } while ($exception = $exception->getPrevious());

        return $output;
    }

    /**
     * Store event
     */
    private function storeEventWithError(MessageEnvelope $envelope, \Throwable $exception): void
    {
        $this->doStoreEventWith($envelope, false, [
            'error_code' => $exception->getCode(),
            'error_message' => $exception->getMessage(),
            'error_trace' => $this->normalizeExceptionTrace($exception),
        ]);
    }

    /**
     * Store event
     */
    private function storeEvent(MessageEnvelope $envelope): void
    {
        $this->doStoreEventWith($envelope, true);
    }

    /**
     * Process synchronously with a transaction
     */
    private function processInTransaction(MessageEnvelope $envelope): void
    {
        if ($this->isTransactionRunning()) {
            // We already have a transaction, we are running within a greater
            // transaction, we let the root transaction handle commit and
            // rollback.
            $this->processWithoutTransaction($envelope);
        } else {
            $transaction = $exception = null;
            $atCommit = false;
            try {
                $transaction = $this->startTransaction();
                $this->synchronousProcess($envelope);
                $atCommit = true;
                $transaction->commit();
                $this->logger->debug("Dispatcher transaction COMMIT");
            } catch (\Throwable $e) {
                $exception = $e;
                if ($transaction) {
                    if ($atCommit) {
                        $this->logger->error("Dispatcher transaction FAIL (at commit), attempting ROLLBACK", ['exception' => $e]);
                    } else {
                        $this->logger->error("Dispatcher transaction FAIL (before commit), attempting ROLLBACK", ['exception' => $e]);
                    }
                    $transaction->rollback($e);
                } else {
                    $this->logger->error("Dispatcher transaction FAIL, no pending transaction");
                }
                throw $e;
            } finally {
                // Store MUST happen in finally, an exception could be rethrowed
                // during rollback, case in which we still need to store event
                // no matter what happens.
                if ($exception) {
                    $this->storeEventWithError($envelope, $exception);
                } else {
                    $this->storeEvent($envelope);
                }
            }
        }
    }

    /**
     * Process without transaction, in most case this means send an asynchronous message
     */
    private function processWithoutTransaction(MessageEnvelope $envelope): void
    {
        try {
            $this->synchronousProcess($envelope);
            $this->storeEvent($envelope);
        } catch (\Throwable $e) {
            $this->storeEventWithError($envelope, $e);
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    final public function dispatchEvent($message, array $properties = []): void
    {
        $id = ++self::$commandCount;
        try {
            $this->logger->debug("Dispatcher BEGIN {id} DISPATCH event", ['id' => $id, 'message' => $message, 'properties' => $properties]);
            $this->doAsynchronousEventDispatch(MessageEnvelope::wrap($message, $properties));
        } finally {
            $this->logger->debug("Dispatcher END {id} DISPATCH event", ['id' => $id]);
        }
    }

    /**
     * {@inheritdoc}
     */
    final public function dispatchCommand($message, array $properties = []): void
    {
        $id = ++self::$commandCount;
        try {
            $this->logger->debug("Dispatcher BEGIN ({id}) DISPATCH command", ['id' => $id, 'message' => $message, 'properties' => $properties]);
            $this->doAsynchronousCommandDispatch(MessageEnvelope::wrap($message, $properties));
        } finally {
            $this->logger->debug("Dispatcher END ({id}) DISPATCH command", ['id' => $id]);
        }
    }

    /**
     * {@inheritdoc}
     */
    final public function dispatch($message, array $properties = []): void
    {
        $id = ++self::$commandCount;
        try {
            $this->logger->debug("Dispatcher BEGIN ({id}) DISPATCH message", ['id' => $id, 'message' => $message, 'properties' => $properties]);
            $this->doAsynchronousCommandDispatch(MessageEnvelope::wrap($message, $properties));
        } finally {
            $this->logger->debug("Dispatcher END ({id}) DISPATCH message", ['id' => $id]);
        }
    }

    /**
     * {@inheritdoc}
     */
    final public function process($message, array $properties = [], bool $withTransaction = true): void
    {
        $id = ++self::$commandCount;
        try {
            $this->logger->debug("Dispatcher BEGIN ({id}) PROCESS message", ['id' => $id, 'message' => $message, 'properties' => $properties]);

            $envelope = MessageEnvelope::wrap($message);

            if ($withTransaction) {
                $this->processInTransaction($envelope);
            } else {
                $this->processWithoutTransaction($envelope);
            }
        } finally {
            $this->logger->debug("Dispatcher END ({id}) PROCESS message", ['id' => $id]);
        }
    }
}
