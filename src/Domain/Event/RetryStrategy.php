<?php

declare(strict_types=1);

namespace Goat\Domain\Event;

interface RetryStrategy
{
    /**
     * Should this message should be retried.
     */
    public function shouldRetry(MessageEnvelope $envelope, \Throwable $error): RetryStrategyResponse;
}
