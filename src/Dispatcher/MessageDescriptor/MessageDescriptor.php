<?php

declare(strict_types=1);

namespace Goat\Dispatcher\MessageDescriptor;

interface MessageDescriptor
{
    /**
     * Outputs a short and human readable description of message.
     *
     * Message can be either an Event from the event store, or a raw command
     * or event raw message that goes throught the bus.
     */
    public function describe($message): ?string;
}
