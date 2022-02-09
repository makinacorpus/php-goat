<?php

declare(strict_types=1);

namespace Goat\Dispatcher\Tests;

use Goat\Dispatcher\Dispatcher;
use Goat\Dispatcher\TransactionHandler;
use Goat\Dispatcher\Decorator\LoggingDispatcherDecorator;
use Goat\Dispatcher\Decorator\ProfilingDispatcherDecorator;
use Goat\Dispatcher\Decorator\RetryDispatcherDecorator;
use Goat\Dispatcher\Decorator\TransactionDispatcherDecorator;
use Goat\Dispatcher\Error\DispatcherRetryableError;
use Goat\Dispatcher\RetryStrategy\DefaultRetryStrategy;
use Goat\MessageBroker\MessageBroker;
use MakinaCorpus\Message\Envelope;
use MakinaCorpus\Message\Property;

final class RetryDispatcherDecoratorTest extends AbstractWithEventStoreTest
{
    public function testProcessDoesNotAttemptRetryOnArbitraryException(): void
    {
        self::expectNotToPerformAssertions();

        $dispatcher = new MockDispatcher(
            static function () { throw new \DomainException(); },
            static function () { throw new \BadMethodCallException(); }
        );

        $dispatcher = $this->decorate(
            $dispatcher,
            static function (Envelope $envelope) {
               throw new \BadMethodCallException("Message should not have been retried.");
            },
            static function (Envelope $envelope) {
                // Do nothing.
            }
        );

        try {
            $dispatcher->process(new MockMessage());
            self::fail();
        } catch (\DomainException $e) {}
    }

    public function testProcessAttempsRetryOnRetryableError(): void
    {
        $retries = [];

        $dispatcher = new MockDispatcher(
            static function () { throw new DispatcherRetryableError(); },
            static function () { throw new \BadMethodCallException(); }
        );

        $dispatcher = $this->decorate(
            $dispatcher,
            static function (Envelope $envelope) use (&$retries) {
                $retries[] = $envelope;
            },
            static function (Envelope $envelope) {
                throw new \BadMethodCallException("Message should be retried, not rejected.");
            }
        );

        $sentMessage = new MockMessage();

        try {
            $dispatcher->process($sentMessage);
            self::fail();
        } catch (DispatcherRetryableError $e) {}

        self::assertCount(1, $retries);

        $envelope = $retries[0];
        \assert($envelope instanceof Envelope);

        self::assertSame($sentMessage, $envelope->getMessage());
        self::assertSame("1", $envelope->getProperty(Property::RETRY_COUNT));
        self::assertSame("100", $envelope->getProperty(Property::RETRY_DELAI));
        self::assertSame("4", $envelope->getProperty(Property::RETRY_MAX));
    }

    public function testProcessDoesNotAttemptRetryWhenMaxReached(): void
    {
        self::expectNotToPerformAssertions();

        $dispatcher = new MockDispatcher(
            static function () { throw new \DomainException(); },
            static function () { throw new \BadMethodCallException(); }
        );

        $dispatcher = $this->decorate(
            $dispatcher,
            static function () { throw new DispatcherRetryableError(); },
            static function (Envelope $envelope) {
                // Do nothing.
            }
        );

        $sentEnvelope = Envelope::wrap(new \DateTimeImmutable(), [
            Property::RETRY_MAX => 4,
            Property::RETRY_COUNT => 4,
        ]);

        try {
            $dispatcher->process($sentEnvelope);
            self::fail();
        } catch (\DomainException $e) {}
    }

    private function decorate(Dispatcher $decorated, callable $retryCallback, callable $rejectCallback): Dispatcher
    {
        return new LoggingDispatcherDecorator(
            new ProfilingDispatcherDecorator(
                new RetryDispatcherDecorator(
                    new TransactionDispatcherDecorator(
                        $decorated,
                        [
                            new class implements TransactionHandler
                            {
                                public function commit(): void
                                {
                                }

                                public function rollback(?\Throwable $previous = null): void
                                {
                                }

                                public function start(): void
                                {
                                }
                            },
                        ]
                    ),
                    new DefaultRetryStrategy(),
                    new class ($retryCallback, $rejectCallback) implements MessageBroker
                    {
                        private $retryCallback;
                        private $rejectCallback;

                        public function __construct(callable $retryCallback, callable $rejectCallback)
                        {
                            $this->retryCallback = $retryCallback;
                            $this->rejectCallback = $rejectCallback;
                        }

                        public function get(): ?Envelope
                        {
                            throw new \BadMethodCallException("We are not testing this.");
                        }

                        public function dispatch(Envelope $envelope): void
                        {
                            throw new \BadMethodCallException("We are not testing this.");
                        }

                        public function ack(Envelope $envelope): void
                        {
                            throw new \BadMethodCallException("We are not testing this.");
                        }

                        public function reject(Envelope $envelope, ?\Throwable $exception = null): void
                        {
                            if ($envelope->hasProperty(Property::RETRY_COUNT)) {
                                ($this->retryCallback)($envelope);
                            } else {
                                ($this->rejectCallback)($envelope);
                            }
                        }
                    }
                )
            )
        );
    }
}
