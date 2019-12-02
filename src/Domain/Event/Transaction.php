<?php

declare(strict_types=1);

namespace Goat\Domain\Event;

/**
 * Reprensents a pending transaction
 */
interface Transaction
{
    /**
     * Commit the transaction
     *
     * @throws \Exception
     *   In case of any error
     */
    public function commit(): void;

    /**
     * Rollback the current transaction
     *
     * @param ?\Throwable $previous
     *   If set, and if you experience an error, you must propagate this previous
     *   exception into your own errors if any
     *
     * @throws \Exception
     *   In case of any error
     */
    public function rollback(?\Throwable $previous = null): void;
}
