<?php

declare(strict_types=1);

namespace Goat\Dispatcher\Message;

/**
 * Message can be retried in case of failure.
 *
 * Set this interface on messages that you know for sure failures are due to
 * context, such as transactions or (un)availability of an third party.
 *
 * When then fail, those messages will be re-queued and re-dispatched later
 * no matter which kind of exception they raise.
 */
interface RetryableMessage
{
}

