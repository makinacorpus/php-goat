<?php

declare(strict_types=1);

namespace Goat\Dispatcher\Message;

/**
 * Message with arbitrary log message
 */
interface WithLogMessage
{
    /**
     * Get log messages
     */
    public function getLogMessage(): ?string;
}
