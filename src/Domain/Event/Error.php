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
 * Errenous data was provided within event.
 */
class InvalidEventData
    extends \InvalidArgumentException
    implements HandlerEventError
{
}

/**
 * Method is disabled.
 */
final class HandlerDisabledException
    implements HandlerConfigurationError
{
}

/**
 * Generic error.
 *
 * @deprecated
 */
class Error extends InvalidEventData
{
}

/**
 * Invalid business data was provider within event.
 */
class HandlerValidationFailed
    extends InvalidEventData
{
}

/**
 * Parallel execution of non parellizable events was attempted.
 */
class ParallelExecutionError
    extends \RuntimeException
    implements HandlerEventError
{
}
