<?php

declare(strict_types=1);

namespace Goat\Dispatcher\Message;

use Ramsey\Uuid\UuidInterface;

/**
 * Base implementation that just stores data into a payload array.
 */
interface Message
{
    /**
     * Get target aggregate identifier
     */
    public function getAggregateId(): UuidInterface;

    /**
     * Get root aggregate identifier
     */
    public function getAggregateRoot(): ?UuidInterface;

    /**
     * Get target aggregate type
     */
    public function getAggregateType(): ?string;
}
