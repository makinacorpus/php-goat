<?php

declare(strict_types=1);

namespace Goat\Dispatcher\Error;

/**
 * Parallel execution of non parellizable events was attempted.
 */
class ParallelExecutionError extends DispatcherError implements HandlerEventError
{
}
