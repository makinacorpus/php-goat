<?php

declare(strict_types=1);

namespace Goat\Dispatcher\Error;

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