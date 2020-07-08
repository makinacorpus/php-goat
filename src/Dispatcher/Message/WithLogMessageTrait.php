<?php

declare(strict_types=1);

namespace Goat\Dispatcher\Message;

/**
 * Default implementation for WithLogMessage.
 */
trait WithLogMessageTrait /* implements WithLogMessage */
{
    private ?string $logMessage = null;

    /**
     * {@inheritdoc}
     */
    public function getLogMessage(): ?string
    {
        return $this->logMessage;
    }
}
