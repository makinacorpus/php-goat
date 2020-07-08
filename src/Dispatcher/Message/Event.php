<?php

declare(strict_types=1);

namespace Goat\Dispatcher\Message;

/**
 * Event is a message that advertise a state change: it happened.
 * It can be consumed by any number of consummers, it should not
 * trigger system state changes, it may or may be not consummed.
 */
interface Event extends Message
{
}
