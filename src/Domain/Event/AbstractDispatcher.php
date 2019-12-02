<?php

declare(strict_types=1);

namespace Goat\Domain\Event;

use Goat\Domain\EventStore\EventStore;
use Goat\Domain\Service\LockService;
use Symfony\Component\Messenger\Envelope;

abstract class AbstractDispatcher implements Dispatcher
{
    private $eventStore;
    private $transaction;
    private $transactionHandlers = [];
    private $transactionHandlersSet = false;
    private $lockService;

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
    final public function setTransactionHandlers(iterable $transactionHandlers)
    {
        if ($this->transactionHandlersSet) {
            throw new \BadMethodCallException("Transactions handlers are already set");
        }
        $this->transactionHandlers = $transactionHandlers;
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
            return $this->transaction;
        }
        return $this->transaction = new DispatcherTransaction($this->transactionHandlers);
    }

    /**
     * Process
     */
    abstract protected function doSynchronousProcess(MessageEnvelope $envelope): ?Envelope;

    /**
     * Send in bus
     */
    abstract protected function doAsynchronousDispatch(MessageEnvelope $envelope): ?Envelope;

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
    private function synchronousProcess(MessageEnvelope $envelope): ?Envelope
    {
        if ($this->lockService) {
            $message = $envelope->getMessage();
            if ($message instanceof UnparallelizableMessage) {
                return $this->processWithLock($envelope, $message->getUniqueIntIdentifier());
            }
        }
        return $this->doSynchronousProcess($envelope);
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
            $this->eventStore->store($message, $id, $type, !$success, [
                'properties' => $envelope->getProperties(),
            ] + $extra);
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
    private function processInTransaction(MessageEnvelope $envelope): ?Envelope
    {
        $ret = null;
        if ($this->isTransactionRunning()) {
            // We already have a transaction, we are running within a greater
            // transaction, we let the root transaction handle commit and
            // rollback.
            $ret = $this->processWithoutTransaction($envelope);
        } else {
            $transaction = $exception = null;
            try {
                $transaction = $this->startTransaction();
                $ret = $this->synchronousProcess($envelope);
                $transaction->commit();
            } catch (\Throwable $e) {
                $exception = $e;
                if ($transaction) {
                    $transaction->rollback($e);
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
        return $ret;
    }

    /**
     * Process without transaction, in most case this means send an asynchronous message
     */
    private function processWithoutTransaction(MessageEnvelope $envelope): ?Envelope
    {
        $ret = null;
        try {
            $ret = $this->synchronousProcess($envelope);
            $this->storeEvent($envelope);
        } catch (\Throwable $e) {
            $this->storeEventWithError($envelope, $e);
            throw $e;
        }
        return $ret;
    }

    /**
     * {@inheritdoc}
     */
    final public function dispatch($message, array $properties = []): ?Envelope
    {
        return $this->doAsynchronousDispatch(MessageEnvelope::wrap($message, $properties));
    }

    /**
     * {@inheritdoc}
     */
    final public function process($message, array $properties = [], bool $withTransaction = true): ?Envelope
    {
        $envelope = MessageEnvelope::wrap($message, $properties);
        if ($withTransaction) {
            return $this->processInTransaction($envelope);
        } else {
            return $this->processWithoutTransaction($envelope);
        }
    }
}
