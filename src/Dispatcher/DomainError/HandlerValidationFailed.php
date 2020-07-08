<?php

declare(strict_types=1);

namespace Goat\Dispatcher\DomainError;

/**
 * Invalid business data was provider within event.
 *
 * @deprecated
 *   Use custom exceptions in your application/domain layer instead.
 */
class HandlerValidationFailed extends InvalidEventData
{
}
