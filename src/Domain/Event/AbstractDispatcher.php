<?php

declare(strict_types=1);

namespace Goat\Domain\Event;

use Goat\Domain\EventStore\Event;
use Goat\Domain\EventStore\EventStore;
use Goat\Domain\EventStore\Property;
use Goat\Domain\Event\Error\DispatcherError;
use Goat\Domain\Event\Error\DispatcherRetryableError;
use Goat\Domain\Projector\ProjectorRegistry;
use Goat\Domain\Service\LockService;
use Goat\Driver\Error\TransactionError;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;

abstract class AbstractDispatcher implements Dispatcher, LoggerAwareInterface
{
    use LoggerAwareTrait;

    const PROP_TIME_START = 'x-goat-time-start';

    private static int $commandCount = 0;

    private ?EventStore $eventStore = null;
    private ?DispatcherTransaction $transaction = null;
    private ?ProjectorRegistry $projectorRegistry = null;
    private ?LockService $lockService = null;
    private iterable $transactionHandlers = [];
    private bool $transactionHandlersSet = false;

    private int $confRetryMax = 4;

    public function __construct()
    {
        $this->logger = new NullLogger();
    }

    /**
     * Convert nano seconds to milliseconds and round the result.
     */
    protected static function nsecToMsec(float $nsec): int
    {
        return (int) ($nsec / 1e+6);
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
    final public function setProjectorRegistry(ProjectorRegistry $projectorRegistry): void
    {
        $this->projectorRegistry = $projectorRegistry;
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
            $this->logger->debug("Dispatcher TRANSACTION SKIP (already running)");

            return $this->transaction;
        }

        $this->logger->debug("Dispatcher TRANSACTION START");

        return $this->transaction = new DispatcherTransaction($this->transactionHandlers);
    }

    /**
     * Requeue message if possible.
     */
    protected function doRequeue(MessageEnvelope $envelope): void
    {
        $this->logger->warning("Dispatcher re-queue is not implemented, skipping retry");
    }

    /**
     * Reject message.
     */
    protected function doReject(MessageEnvelope $envelope): void
    {
        $this->logger->warning("Dispatcher reject is not implemented, skipping reject");
    }

    /**
     * Process.
     */
    abstract protected function doSynchronousProcess(MessageEnvelope $envelope): void;

    /**
     * Send in bus
     */
    abstract protected function doAsynchronousCommandDispatch(MessageEnvelope $envelope): void;

    /**
     * Send in bus
     */
    abstract protected function doAsynchronousEventDispatch(MessageEnvelope $envelope): void;

    /**
     * Requeue message if possible.
     *
     * Returns the updated envelope in case headers were changed.
     */
    private function requeue(MessageEnvelope $envelope): MessageEnvelope
    {
        $count = (int)$envelope->getProperty(Property::RETRY_COUNT, "0");
        $delay = (int)$envelope->getProperty(Property::RETRY_MAX, "100");
        $max = (int)$envelope->getProperty(Property::RETRY_MAX, (string)$this->confRetryMax);

        if ($count >= $max) {
            return $this->doReject($envelope);
        }

        $envelope = $envelope->withProperties([
            Property::RETRY_COUNT => $count + 1,
            Property::RETRY_MAX => $max,
            Property::RETRY_DELAI => $delay * ($count + 1),
        ]);

        // Arbitrary delai. Yes, very arbitrary.
        $this->doRequeue($envelope);

        return $envelope;
    }

    /**
     * Reject message.
     *
     * Returns the updated envelope in case headers were changed.
     */
    private function reject(MessageEnvelope $envelope): MessageEnvelope
    {
        // Rest all routing information, so that the broker will not take
        // those into account if some were remaining.
        $envelope = $envelope->withProperties([
            Property::RETRY_COUNT => null,
            Property::RETRY_MAX => null,
            Property::RETRY_DELAI => null,
        ]);

        $this->doReject($envelope);

        return $envelope;
    }

    /**
     * Handle Projectors
     */
    private function handleProjectors(Event $event): void
    {
        if ($this->projectorRegistry) {
            foreach ($this->projectorRegistry->getEnabled() as $projector) {
                try {
                    $this->logger->debug("Projector {projector} BEGIN PROCESS message", ['projector' => \get_class($projector), 'message' => $event->getMessage()]);
                    $projector->onEvent($event);
                    $this->logger->debug("Projector {projector} END PROCESS message", ['projector' => \get_class($projector), 'message' => $event->getMessage()]);
                } catch (\Throwable $e) {
                    $this->logger->error("Projector {projector} FAIL", ['projector' => \get_class($projector), 'exception' => $e]);
                }
            }
        }
    }

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
        try {
            if ($this->lockService) {
                $message = $envelope->getMessage();
                if ($message instanceof UnparallelizableMessage) {
                    $this->processWithLock($envelope, $message->getUniqueIntIdentifier());
                    return;
                }
            }
            $this->doSynchronousProcess($envelope);
        } catch (DispatcherError $e) {
            // This should not happen, but the handler might already have
            // specialized the exception, just rethrow it.
            $this->logger->warning("Failure is already specialized as a dispatcher error.", ['exception' => $e]);
            throw $e;
        } catch (TransactionError $e) {
            // Error is related to database transaction, just mark it for
            // re-queue.
            $this->logger->debug("Failure is retryable (from exception).", ['exception' => $e]);
            throw DispatcherRetryableError::fromException($e);
        } catch (\Throwable $e) {
            // Generic error, attempt to specialize exception by reading
            // message type information.
            if ($envelope->getMessage() instanceof RetryableMessage) {
                $this->logger->debug("Failure is retryable (from message).", ['exception' => $e]);
                throw DispatcherRetryableError::fromException($e);
            }
            throw DispatcherError::fromException($e);
        }
    }

    /**
     * Really store event callback.
     */
    private function createEvent(MessageEnvelope $envelope): ?Event
    {
        if (!$this->eventStore) {
            return null;
        }

        $builder = $this->eventStore->append($envelope->getMessage());

        foreach ($envelope->getProperties() as $name => $value) {
            $builder->property($name, $value);
        }

        return $builder->execute();
    }

    /**
     * Handle event update with success.
     */
    private function updateEventWithSuccess(MessageEnvelope $envelope, ?Event $event): void
    {
        if ($event && $this->eventStore) {

            $properties = $envelope->getProperties();
            $properties[Property::PROCESS_DURATION] = self::computeDuration($envelope);
            $properties[self::PROP_TIME_START] = null;

            $event = $this
                ->eventStore
                ->update($event)
                ->properties($properties)
                ->execute()
            ;

            // Handle projectors cannot raise exception, it will not interfere
            // with custom error handling.
            $this->handleProjectors($event);
        }
    }

    /**
     * Handle event update with failure.
     */
    private function updateEventWithFailure(MessageEnvelope $envelope, ?Event $event, \Throwable $exception): void
    {
        if ($event && $this->eventStore) {

            $properties = $envelope->getProperties();
            $properties[Property::PROCESS_DURATION] = self::computeDuration($envelope);
            $properties[self::PROP_TIME_START] = null;

            $this
                ->eventStore
                ->failedWith($event, $exception)
                ->properties($properties)
                ->execute()
            ;
        }
    }

    /**
     * Compute some additional metadata for event.
     */
    private function computeDuration(MessageEnvelope $envelope): ?string
    {
        if ($envelope->hasProperty(self::PROP_TIME_START)) {
            $start = (int)$envelope->getProperty(self::PROP_TIME_START);

            return self::nsecToMsec(\hrtime(true) - $start) . ' ms';
        }
        return null;
    }

    /**
     * Process synchronously with a transaction.
     */
    private function processInTransaction(MessageEnvelope $envelope): void
    {
        if ($this->isTransactionRunning()) {
            // We already have a transaction, we are running within a greater
            // transaction, we let the root transaction handle commit and
            // rollback.
            $this->processWithoutTransaction($envelope);

            return;
        }

        $event = $this->createEvent($envelope);

        $transaction = null;
        $atCommit = false;
        try {
            $transaction = $this->startTransaction();
            $this->synchronousProcess($envelope);
            $atCommit = true;
            $transaction->commit();
            $this->logger->debug("Dispatcher transaction COMMIT");
            $this->updateEventWithSuccess($envelope, $event);
        } catch (\Throwable $e) {
            // Log as meaningful as we can, this is a very hard part to debug
            // so output the most as we can for future developers that will
            // try to guess what happened in production.
            if ($transaction) {
                if ($atCommit) {
                    $this->logger->error("Dispatcher transaction FAIL (at commit), attempting ROLLBACK", ['exception' => $e]);
                } else {
                    $this->logger->error("Dispatcher transaction FAIL (before commit), attempting ROLLBACK", ['exception' => $e]);
                }
                $transaction->rollback();
            } else {
                $this->logger->error("Dispatcher transaction FAIL, no pending transaction");
            }

            // Attempt retry if possible.
            try {
                if ($e instanceof DispatcherRetryableError) {
                    $envelope = $this->requeue($envelope);
                    $this->logger->debug("Dispatcher requeue");
                } else {
                    $envelope = $this->reject($envelope);
                    $this->logger->debug("Dispatcher reject");
                }
            } catch (\Throwable $nested) {
                $this->logger->error("Dispatcher re-queue FAIL", ['exception' => $nested]);
            }

            $this->updateEventWithFailure($envelope, $event, $e);

            throw $e;
        }
    }

    /**
     * Process without transaction, in most case this means send an asynchronous message.
     */
    private function processWithoutTransaction(MessageEnvelope $envelope): void
    {
        $event = $this->createEvent($envelope);

        try {
            $this->synchronousProcess($envelope);
            $this->updateEventWithSuccess($envelope, $event);
        } catch (\Throwable $e) {
            $this->updateEventWithFailure($envelope, $event, $e);

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

            $properties[self::PROP_TIME_START] = \hrtime(true);
            $envelope = MessageEnvelope::wrap($message, $properties);

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
