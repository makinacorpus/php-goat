<?php

declare(strict_types=1);

namespace Goat\Dispatcher\Message;

/**
 * Messages of the same type (ie. class name) implementing this interface cannot
 * run in parallele and will be blocked. Any blocked message will fail and will
 * not be retried.
 */
interface UnparallelizableMessage
{
    /**
     * Unique identifier, every cron that have the same identifier
     * will lock each others, be careful when attributing a new
     * identifier within the same application.
     */
    public function getUniqueIntIdentifier(): int;
}
