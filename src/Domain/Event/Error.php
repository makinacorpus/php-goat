<?php

declare(strict_types=1);

namespace Goat\Domain\Event\Error;

/**
 * Errors related to runtime errors while processing events.
 */
interface HandlerEventError
{
}

/**
 * Errors related to configuration.
 */
interface HandlerConfigurationError
{
}

/**
 * Error happened during event process.
 */
class DispatcherError extends \RuntimeException
{
    /**
     * Create instance from existing exception.
     */
    public static function fromException(\Throwable $e): self
    {
        if ($e instanceof self) {
            return $e;
        }
        return new static($e->getMessage(), $e->getCode(), $e);
    }
}

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

/**
 * Errenous data was provided within event.
 */
class InvalidEventData extends DispatcherError implements HandlerEventError
{
}

/**
 * Method is disabled.
 */
final class HandlerDisabledException extends DispatcherError implements HandlerConfigurationError
{
}

/**
 * Generic error.
 *
 * @deprecated
 */
class Error extends InvalidEventData implements HandlerEventError
{
}

/**
 * Invalid business data was provider within event.
 */
class HandlerValidationFailed extends InvalidEventData
{
}

/**
 * Parallel execution of non parellizable events was attempted.
 */
class ParallelExecutionError extends DispatcherError implements HandlerEventError
{
}
