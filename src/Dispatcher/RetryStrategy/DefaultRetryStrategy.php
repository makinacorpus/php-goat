<?php

declare(strict_types=1);

namespace Goat\Dispatcher\RetryStrategy;

use Goat\Dispatcher\Error\DispatcherRetryableError;
use Goat\Driver\Error\TransactionError;
use MakinaCorpus\Message\Envelope;

final class DefaultRetryStrategy implements RetryStrategy
{
    /**
     * {@inheritdoc}
     */
    public function shouldRetry(Envelope $envelope, \Throwable $error): RetryStrategyResponse
    {
        if ($error instanceof TransactionError) {
            return RetryStrategyResponse::retry("Transaction serialization failure");
        }
        if ($error instanceof DispatcherRetryableError) {
            return RetryStrategyResponse::retry("Dispatcher specialized error");
        }
        /*
         * @todo
         *   Restore this feature using attributes.
         *
        if ($envelope->getMessage() instanceof RetryableMessage) {
            return RetryStrategyResponse::retry("Message specialized as retryable");
        }
         */

        return RetryStrategyResponse::reject();
    }
}