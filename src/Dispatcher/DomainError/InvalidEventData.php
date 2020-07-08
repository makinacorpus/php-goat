<?php

declare(strict_types=1);

namespace Goat\Dispatcher\DomainError;

use Goat\Dispatcher\Error\DispatcherError;
use Goat\Dispatcher\Error\HandlerEventError;

/**
 * Errenous data was provided within event.
 *
 * @deprecated
 *   Use custom exceptions in your application/domain layer instead.
 */
class InvalidEventData extends DispatcherError implements HandlerEventError
{
}
