<?php

declare(strict_types=1);

namespace Goat\Dispatcher\Decorator;

use Goat\Dispatcher\Dispatcher;
use Goat\Dispatcher\MessageEnvelope;
use Goat\Dispatcher\TransactionHandler;
use Goat\Dispatcher\RetryStrategy\RetryStrategy;
use Goat\Dispatcher\RetryStrategy\RetryStrategyResponse;
use Goat\EventStore\Property;
use Goat\MessageBroker\MessageBroker;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;

final class RetryDispatcherDecorator implements Dispatcher, LoggerAwareInterface
{
    use LoggerAwareTrait;

    private Dispatcher $decorated;
    private MessageBroker $messageBroker;
    private RetryStrategy $retryStrategy;
    private static int $commandCount = 0;

    /** @param TransactionHandler $transactionHandlers */
    public function __construct(Dispatcher $decorated, RetryStrategy $retryStrategy, MessageBroker $messageBroker)
    {
        $this->decorated = $decorated;
        $this->logger = new NullLogger();
        $this->messageBroker = $messageBroker;
        $this->retryStrategy = $retryStrategy;
    }

    /**
     * Synchronous process means we are doing the business transaction.
     *
     * At this point, event must be created and stored.
     *
     * {@inheritdoc}
     */
    public function process($message, array $properties = []): void
    {
        $envelope = MessageEnvelope::wrap($message, $properties);

        try {
            $this->decorated->process($envelope);
        } catch (\Throwable $e) {
            $response = $this->retryStrategy->shouldRetry($envelope, $e);

            if ($response->shouldRetry()) {
                $this->logger->debug("Failure is retryable.", ['exception' => $e]);

                $this->doRequeue($envelope, $response);
            } else {
                $this->logger->debug("Failure is not retryable.", ['exception' => $e]);

                $this->doReject($envelope);
            }

            throw $e;
        }
    }

    /**
     * Dispatch means we are NOT processing the business transaction but
     * queuing it into the bus, do nothing.
     *
     * {@inheritdoc}
     */
    public function dispatch($message, array $properties = []): void
    {
        $this->decorated->dispatch($message, $properties);
    }

    /**
     * Requeue message if possible.
     */
    protected function doRequeue(MessageEnvelope $envelope, RetryStrategyResponse $response): void
    {
        $count = (int)$envelope->getProperty(Property::RETRY_COUNT, "0");
        $delay = (int)$envelope->getProperty(Property::RETRY_DELAI, (string)$response->getDelay());
        $max = (int)$envelope->getProperty(Property::RETRY_MAX, (string)$response->getMaxCount());

        if ($count >= $max) {
            $this->doReject($envelope);

            return;
        }

        // Arbitrary delai. Yes, very arbitrary.
        $this->messageBroker->reject(
            $envelope->withProperties([
                Property::RETRY_COUNT => $count + 1,
                Property::RETRY_DELAI => $delay * ($count + 1),
                Property::RETRY_MAX => $max,
                Property::RETRY_REASON => $response->getReason(),
            ])
        );
    }

    /**
     * Reject message.
     */
    protected function doReject(MessageEnvelope $envelope): void
    {
        // Rest all routing information, so that the broker will not take
        // those into account if some were remaining.
        $this->messageBroker->reject(
            $envelope->withProperties([
                Property::RETRY_COUNT => null,
                Property::RETRY_DELAI => null,
                Property::RETRY_MAX => null,
                Property::RETRY_REASON => null,
            ])
        );
    }
}
