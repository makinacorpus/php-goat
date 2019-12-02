<?php

declare(strict_types=1);

namespace Goat\Domain\Event;

/**
 * Reprensents a pending transaction
 */
interface TransactionHandler extends Transaction
{
    /**
     * Start transaction
     */
    public function start(): void;
}
