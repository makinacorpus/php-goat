<?php

declare(strict_types=1);

namespace Goat\Dispatcher\Decorator;

use Goat\Dispatcher\Dispatcher;
use Goat\Dispatcher\TransactionHandler;
use Goat\Dispatcher\RetryStrategy\RetryStrategy;
use Goat\Dispatcher\RetryStrategy\RetryStrategyResponse;
use Goat\MessageBroker\MessageBroker;
use MakinaCorpus\Message\Envelope;
use MakinaCorpus\Message\Property;
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
        $envelope = Envelope::wrap($message, $properties);

        try {
            $this->decorated->process($envelope);
        } catch (\Throwable $e) {
            // Honnor retry killswitch.
            if ($envelope->hasProperty(Property::RETRY_KILLSWITCH)) {
                throw $e;
            }

            $response = $this->retryStrategy->shouldRetry($envelope, $e);

            if ($response->shouldRetry()) {
                $this->logger->debug("Failure is retryable.", ['exception' => $e]);

                $this->doRequeue($envelope, $response, $e);
            } else {
                $this->logger->debug("Failure is not retryable.", ['exception' => $e]);

                $this->doReject($envelope, $e);
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
    protected function doRequeue(Envelope $envelope, RetryStrategyResponse $response, ?\Throwable $exception = null): void
    {
        $count = (int)$envelope->getProperty(Property::RETRY_COUNT, "0");
        $delay = (int)$envelope->getProperty(Property::RETRY_DELAI, (string)$response->getDelay());
        $max = (int)$envelope->getProperty(Property::RETRY_MAX, (string)$response->getMaxCount());

        if ($count >= $max) {
            $this->doReject($envelope, $exception);

            return;
        }

        // Arbitrary delai. Yes, very arbitrary.
        $this->messageBroker->reject(
            $envelope->withProperties([
                Property::RETRY_COUNT => $count + 1,
                Property::RETRY_DELAI => $delay * ($count + 1),
                Property::RETRY_MAX => $max,
                Property::RETRY_REASON => $response->getReason(),
            ]),
            $exception
        );
    }

    /**
     * Reject message.
     */
    protected function doReject(Envelope $envelope, ?\Throwable $exception = null): void
    {
        // Rest all routing information, so that the broker will not take
        // those into account if some were remaining.
        $this->messageBroker->reject(
            $envelope->withProperties([
                Property::RETRY_COUNT => null,
                Property::RETRY_DELAI => null,
                Property::RETRY_MAX => null,
                Property::RETRY_REASON => null,
            ]),
            $exception
        );
    }
}
