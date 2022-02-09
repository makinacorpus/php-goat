<?php

declare(strict_types=1);

namespace Goat\Dispatcher\Decorator;

use Goat\Dispatcher\Dispatcher;
use Goat\Dispatcher\DispatcherTransaction;
use Goat\Dispatcher\Transaction;
use Goat\Dispatcher\TransactionHandler;
use Goat\Driver\Error\TransactionError;
use MakinaCorpus\Message\Envelope;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;

final class TransactionDispatcherDecorator implements Dispatcher, LoggerAwareInterface
{
    use LoggerAwareTrait;

    private Dispatcher $decorated;
    private ?DispatcherTransaction $transaction = null;
    private iterable $transactionHandlers = [];
    private bool $transactionHandlersSet = false;

    /** @param TransactionHandler $transactionHandlers */
    public function __construct(Dispatcher $decorated, iterable $transactionHandlers)
    {
        $this->decorated = $decorated;
        $this->transactionHandlers = $transactionHandlers;
        $this->logger = new NullLogger();
    }

    /**
     * Synchronous process means we are doing the business transaction.
     *
     * At this point, event must be created and stored.
     *
     * {@inheritdoc}
     */
    public function process($message, array $properties = []): void
    {
        if ($this->isTransactionRunning()) {
            // We already have a transaction, we are running within a greater
            // transaction, we let the root transaction handle commit and
            // rollback.
            $this->decorated->process($message, $properties);

            return;
        }

        $envelope = Envelope::wrap($message, $properties);
        $transaction = null;
        $atCommit = false;

        try {
            $transaction = $this->startTransaction();
            $this->decorated->process($envelope);
            $atCommit = true;
            $transaction->commit();
            $this->logger->debug("Dispatcher TRANSACTION COMMIT");
        } catch (\Throwable $e) {
            // Log as meaningful as we can, this is a very hard part to debug
            // so output the most as we can for future developers that will
            // try to guess what happened in production.
            if ($transaction) {
                if ($atCommit) {
                    $this->logger->error("Dispatcher TRANSACTION FAIL (at commit), attempting ROLLBACK", ['exception' => $e]);
                } else {
                    $this->logger->error("Dispatcher TRANSACTION FAIL (before commit), attempting ROLLBACK", ['exception' => $e]);
                }
                $transaction->rollback();
            } else {
                $this->logger->error("Dispatcher TRANSACTION FAIL, no pending transaction");
            }

            throw $e;
        }
    }

    /**
     * Dispatch means we are NOT processing the business transaction but
     * queuing it into the bus, do nothing.
     *
     * {@inheritdoc}
     */
    public function dispatch($message, array $properties = []): void
    {
        $this->decorated->dispatch($message, $properties);
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
            throw new TransactionError("You cannot have more than one transaction at the same time.");
        }

        $this->logger->debug("Dispatcher TRANSACTION START");

        return $this->transaction = new DispatcherTransaction($this->transactionHandlers);
    }
}
