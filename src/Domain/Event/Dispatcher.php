<?php

declare(strict_types=1);

namespace Goat\Domain\Event;

use Goat\Domain\EventStore\EventStore;
use Goat\Domain\Service\LockService;
use Symfony\Component\Messenger\Envelope;

/**
 * Dispatcher that serves as a facade to send command and events to the bus.
 * This piece is centric within the application.
 *
 * It also will dispatch differently if there are pending transactions.
 */
interface Dispatcher
{
    /**
     * Set transaction handlers
     *
     * @internal
     */
    public function setTransactionHandlers(iterable $transactionHandlers);

    /**
     * Set event store
     */
    public function setEventStore(EventStore $eventStore): void;

    /**
     * Set Locking Service
     */
    public function setLockService(LockService $lockService): void;

    /**
     * Same as dispatch() but do not respect transactions, no matter if there
     * is anything pending, it will be delt with outside of the transaction
     * asynchronously.
     */
    public function dispatch($message, array $properties = []): ?Envelope;

    /**
     * Process command synchronously
     */
    public function process($message, array $properties = [], bool $withTransaction = true): ?Envelope;
}
