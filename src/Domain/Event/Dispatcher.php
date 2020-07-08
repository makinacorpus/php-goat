<?php

declare(strict_types=1);

namespace Goat\Domain\Event;

/**
 * Dispatcher that serves as a facade to send command and events to the bus.
 * This piece is centric within the application.
 *
 * It also will dispatch differently if there are pending transactions.
 */
interface Dispatcher
{
    /**
     * Dispatch command asynchronously (via the bus).
     */
    public function dispatchCommand($message, array $properties = []): void;

    /**
     * Dispatch command asynchronously (via the bus).
     *
     * @deprecated
     * @see self::dispatchCommand()
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
