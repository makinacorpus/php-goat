<?php

declare(strict_types=1);

namespace Goat\Projector\Runtime;

use Goat\EventStore\Event;

interface RuntimePlayer
{
    /**
     * Play a single event over all projectors.
     */
    public function dispatch(Event $event): void;
}
