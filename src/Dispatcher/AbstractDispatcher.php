<?php

declare(strict_types=1);

namespace Goat\Dispatcher;

use Goat\Dispatcher\Error\DispatcherError;
use Goat\Dispatcher\Error\DispatcherRetryableError;
use Goat\Driver\Error\TransactionError;
use Goat\EventStore\Property;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;

abstract class AbstractDispatcher implements Dispatcher, LoggerAwareInterface
{
    use LoggerAwareTrait;

    private static int $commandCount = 0;

    private ?DispatcherTransaction $transaction = null;
    private iterable $transactionHandlers = [];
    private bool $transactionHandlersSet = false;

    private int $confRetryMax = 4;

    public function __construct()
    {
        $this->logger = new NullLogger();
    }

    /**
     * Set transaction handlers.
     *
     * @internal
     */
    final public function setTransactionHandlers(iterable $transactionHandlers): void
    {
        if ($this->transactionHandlersSet) {
            throw new \BadMethodCallException("Transactions handlers are already set");
        }
        $this->transactionHandlers = $transactionHandlers;
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
     * Requeue message if possible.
     *
     * Returns the updated envelope in case headers were changed.
     */
    private function requeue(MessageEnvelope $envelope): void
    {
        $count = (int)$envelope->getProperty(Property::RETRY_COUNT, "0");
        $delay = (int)$envelope->getProperty(Property::RETRY_MAX, "100");
        $max = (int)$envelope->getProperty(Property::RETRY_MAX, (string)$this->confRetryMax);

        if ($count >= $max) {
            $this->doReject($envelope);

            return;
        }

        $envelope = $envelope->withProperties([
            Property::RETRY_COUNT => $count + 1,
            Property::RETRY_MAX => $max,
            Property::RETRY_DELAI => $delay * ($count + 1),
        ]);

        // Arbitrary delai. Yes, very arbitrary.
        $this->doRequeue($envelope);
    }

    /**
     * Reject message.
     *
     * Returns the updated envelope in case headers were changed.
     */
    private function reject(MessageEnvelope $envelope): void
    {
        // Rest all routing information, so that the broker will not take
        // those into account if some were remaining.
        $envelope = $envelope->withProperties([
            Property::RETRY_COUNT => null,
            Property::RETRY_MAX => null,
            Property::RETRY_DELAI => null,
        ]);

        $this->doReject($envelope);
    }

    /**
     * Call doSynchronousProcess but checks for Parallel/Lock potential problems before
     */
    private function synchronousProcess(MessageEnvelope $envelope): void
    {
        try {
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

        $transaction = null;
        $atCommit = false;

        try {
            $transaction = $this->startTransaction();
            $this->synchronousProcess($envelope);
            $atCommit = true;
            $transaction->commit();
            $this->logger->debug("Dispatcher transaction COMMIT");
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
                    $this->requeue($envelope);
                    $this->logger->debug("Dispatcher requeue");
                } else {
                    $this->reject($envelope);
                    $this->logger->debug("Dispatcher reject");
                }
            } catch (\Throwable $nested) {
                $this->logger->error("Dispatcher re-queue FAIL", ['exception' => $nested]);
            }

            throw $e;
        }
    }

    /**
     * Process without transaction, in most case this means send an asynchronous message.
     */
    private function processWithoutTransaction(MessageEnvelope $envelope): void
    {
        $this->synchronousProcess($envelope);
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
