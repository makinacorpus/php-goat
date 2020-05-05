<?php

declare(strict_types=1);

namespace Goat\Domain\Projector;

use Goat\Domain\EventStore\Event;

/**
 * Projectors will be process after an event has been consume.
 * It's mainly used to statistics purpose.
 */
interface Projector
{
    /**
     * @var string identifier of your projector, should be unique.
     */
    public $identifier;

    /**
     * This method will be call after a message has been stored.
     */
    public function onEvent(Event $event);

    /**
     * Get last processed event date.
     *
     * @return ?\DateTimeInterface Null if Projector has never been processed
     */
    public function getLastProcessedEventDate(): ?\DateTimeInterface;
}
