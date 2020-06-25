<?php

declare(strict_types=1);

namespace Goat\Domain\Projector;

use Goat\Domain\EventStore\Event;

/**
 * Projectors will be process after an event has been consumed.
 * It's mainly used to statistics purpose.
 *
 * Projector will consume events, sometime, events can be sent more than once
 * so implementation must be able to detect that, ensuring event consumption
 * to be reentrant.
 */
interface Projector
{
    /**
     * Get projector identifier, should be unique.
     */
    public function getIdentifier(): string;

    /**
     * This method will be call after a message has been stored.
     */
    public function onEvent(Event $event): void;

    /**
     * Get last processed event date.
     *
     * @return ?\DateTimeInterface Null if Projector has never been processed
     */
    public function getLastProcessedEventDate(): ?\DateTimeInterface;

    /**
     * Get handled events name list.
     *
     * @return null|string[]
     *   Returned event names can be either event names or PHP class names.
     */
    public function getHandledEvents(): ?array;
}
