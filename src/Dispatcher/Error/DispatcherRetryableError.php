<?php

declare(strict_types=1);

namespace Goat\Dispatcher\Error;

/**
 * Error is retryable.
 *
 * When running a message handler, exceptions will be introspected, if it is
 * related to a transaction isolation kind of error, it will be wrapped into
 * this exception.
 *
 * It might also be triggered if the message implemens the RetryableMessage
 * interface.
 *
 * Our message bus transport, which is agnostic from this namespace, will
 * find out its own Retryable error interface and re-queue event.
 */
class DispatcherRetryableError extends DispatcherError implements HandlerEventError
{
}
