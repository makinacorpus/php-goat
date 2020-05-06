<?php

declare(strict_types=1);

namespace Goat\Domain\Event;

use Goat\Domain\EventStore\EventStore;
use Goat\Domain\Projector\ProjectorRegistry;
use Goat\Domain\Service\LockService;

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
    public function setTransactionHandlers(iterable $transactionHandlers): void;

    /**
     * Set event store
     */
    public function setEventStore(EventStore $eventStore): void;

    /**
     * Set Locking Service
     */
    public function setLockService(LockService $lockService): void;

    /**
     * Set ProjectorRegistry
     */
    public function setProjectorRegistry(ProjectorRegistry $projectorRegistry): void;

    /**
     * Dispatch event asynchronously (via the bus).
     */
    public function dispatchEvent($message, array $properties = []): void;

    /**
     * Dispatch command asynchronously (via the bus).
     */
    public function dispatchCommand($message, array $properties = []): void;

    /**
     * Dispatch command asynchronously (via the bus).
     *
     * @deprecated
     *   Use dispatchCommand() instead.
     */
    public function dispatch($message, array $properties = []): void;

    /**
     * Process command synchronously.
     *
     * It can work only if the command was meant to be consumed within the same
     * application, otherwise it will be rejected, and fail.
     */
    public function process($message, array $properties = [], bool $withTransaction = true): void;
}
