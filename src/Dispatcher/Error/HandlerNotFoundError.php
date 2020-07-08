<?php

declare(strict_types=1);

namespace Goat\Dispatcher\Error;

/**
 * Handler not found.
 */
class HandlerNotFoundError extends \RuntimeException implements HandlerEventError
{
}
