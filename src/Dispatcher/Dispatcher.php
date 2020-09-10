<?php

declare(strict_types=1);

namespace Goat\Dispatcher;

/**
 * Dispatcher that serves as a facade to send command and events to the bus.
 * This piece is centric within the application.
 *
 * It also will dispatch differently if there are pending transactions.
 *
 * Major breaking changes from previous versions:
 *
 *   - all dependency setters has been removed from the interface, compiler
 *     passes and extension are now smarter and inject those only in case
 *     it's necessary,
 *
 *   - dispatchEvent() method has been removed, nobody ever used it,
 *
 *   - dispatchCommand() have been removed from the interface.
 */
interface Dispatcher
{
    /**
     * Dispatch command asynchronously (via the bus).
     */
    public function dispatch($message, array $properties = []): void;

    /**
     * Process command synchronously.
     *
     * It can work only if the command was meant to be consumed within the same
     * application, otherwise it will be rejected, and fail.
     */
    public function process($message, array $properties = []): void;
}
