<?php

declare(strict_types=1);

namespace Goat\Dispatcher\Message;


/**
 * Command is a message that triggers a change: it has not happened yet.
 * It can only be sent a single consumer, the only exception is in case
 * of failure it may be retried by someone else.
 */
interface Command extends Message
{
}
