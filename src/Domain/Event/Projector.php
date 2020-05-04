<?php

declare(strict_types=1);

namespace Goat\Domain\Event;

use Goat\Domain\EventStore\Event;

/**
 * Projectors will be process after an event has been consume.
 * It's mainly used to statistics purpose.
 */
interface Projector
{
    public function onEvent(Event $event);
}
