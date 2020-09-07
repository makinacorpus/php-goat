<?php

declare(strict_types=1);

namespace Goat\Dispatcher\RetryStrategy;

use Goat\Dispatcher\MessageEnvelope;
use Goat\Dispatcher\Error\DispatcherRetryableError;
use Goat\Dispatcher\Message\RetryableMessage;
use Goat\Driver\Error\TransactionError;

final class DefaultRetryStrategy implements RetryStrategy
{
    /**
     * {@inheritdoc}
     */
    public function shouldRetry(MessageEnvelope $envelope, \Throwable $error): RetryStrategyResponse
    {
        if ($error instanceof TransactionError) {
            return RetryStrategyResponse::retry("Transaction serialization failure");
        }
        if ($error instanceof DispatcherRetryableError) {
            return RetryStrategyResponse::retry("Dispatcher specialized error");
        }
        if ($envelope->getMessage() instanceof RetryableMessage) {
            return RetryStrategyResponse::retry("Message specialized as retryable");
        }

        return RetryStrategyResponse::reject();
    }
}