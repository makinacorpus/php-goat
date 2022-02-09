<?php

declare(strict_types=1);

namespace Goat\Dispatcher\RetryStrategy;

use MakinaCorpus\Message\Envelope;

interface RetryStrategy
{
    /**
     * Should this message should be retried.
     */
    public function shouldRetry(Envelope $envelope, \Throwable $error): RetryStrategyResponse;
}
