<?php

declare(strict_types=1);

namespace Goat\Dispatcher\RetryStrategy;

use Goat\Dispatcher\MessageEnvelope;

interface RetryStrategy
{
    /**
     * Should this message should be retried.
     */
    public function shouldRetry(MessageEnvelope $envelope, \Throwable $error): RetryStrategyResponse;
}
