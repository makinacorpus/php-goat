<?php

declare(strict_types=1);

namespace Goat\Dispatcher\Tests;

use Goat\Dispatcher\Dispatcher;
use Goat\Dispatcher\MessageEnvelope;
use Goat\Dispatcher\TransactionHandler;
use Goat\Dispatcher\Decorator\EventStoreDispatcherDecorator;
use Goat\Dispatcher\Decorator\LoggingDispatcherDecorator;
use Goat\Dispatcher\Decorator\ProfilingDispatcherDecorator;
use Goat\Dispatcher\Decorator\RetryDispatcherDecorator;
use Goat\Dispatcher\Decorator\TransactionDispatcherDecorator;
use Goat\Dispatcher\Error\DispatcherRetryableError;
use Goat\Dispatcher\RetryStrategy\DefaultRetryStrategy;
use Goat\EventStore\Event;
use Goat\EventStore\Property;
use Goat\EventStore\Tests\AbstractEventStoreTest;
use Goat\MessageBroker\MessageBroker;

final class RetryDispatcherDecoratorTest extends AbstractEventStoreTest
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
            static function (MessageEnvelope $envelope) {
               throw new \BadMethodCallException("Message should not have been retried.");
            },
            static function (MessageEnvelope $envelope) {
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
            static function (MessageEnvelope $envelope) use (&$retries) {
                $retries[] = $envelope;
            },
            static function (MessageEnvelope $envelope) {
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
        \assert($envelope instanceof MessageEnvelope);

        self::assertSame($sentMessage, $envelope->getMessage());
        self::assertSame("1", $envelope->getProperty(Property::RETRY_COUNT));
        self::assertSame("100", $envelope->getProperty(Property::RETRY_DELAI));
        self::assertSame("4", $envelope->getProperty(Property::RETRY_MAX));
    }

    public function testProcessAttempsRetryOnRetryableMessage(): void
    {
        $retries = [];

        $dispatcher = new MockDispatcher(
            static function () { throw new \DomainException(); },
            static function () { throw new \BadMethodCallException(); }
        );

        $dispatcher = $this->decorate(
            $dispatcher,
            static function (MessageEnvelope $envelope) use (&$retries) {
                $retries[] = $envelope;
            },
            static function (MessageEnvelope $envelope) {
                throw new \BadMethodCallException("Message should be retried, not rejected.");
            }
        );

        $sentMessage = new MockRetryableMessage();

        try {
            $dispatcher->process($sentMessage);
            self::fail();
        } catch (\DomainException $e) {}

        self::assertCount(1, $retries);

        $envelope = $retries[0];
        \assert($envelope instanceof MessageEnvelope);

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
            static function (MessageEnvelope $envelope) {
               throw new \BadMethodCallException("Message should not have been retried.");
            },
            static function (MessageEnvelope $envelope) {
                // Do nothing.
            }
        );

        $sentEnvelope = MessageEnvelope::wrap(new MockRetryableMessage(), [
            Property::RETRY_MAX => 4,
            Property::RETRY_COUNT => 4,
        ]);

        try {
            $dispatcher->process($sentEnvelope);
            self::fail();
        } catch (\DomainException $e) {}
    }

    public function testProcessStoreFailedEventWhenRetry(): void
    {
        $decorated = new MockDispatcher(
            static function () { throw new \DomainException(); },
            static function () { throw new \BadMethodCallException(); }
        );

        $eventStore = new MockEventStore();

        $dispatcher = $this->decorate(
            $decorated,
            static function (MessageEnvelope $envelope) {
                // It should pass here. That's not what we test.
            },
            static function (MessageEnvelope $envelope) {
                throw new \BadMethodCallException("Message should be retried, not rejected.");
            }
        );

        $dispatcher = new EventStoreDispatcherDecorator($dispatcher, $eventStore);

        $sentMessage = new MockRetryableMessage();

        try {
            $dispatcher->process($sentMessage);
            self::fail();
        } catch (\DomainException $e) {}


        $storedEvents = $eventStore->getStored();

        self::assertCount(1, $storedEvents);

        $envelope = $storedEvents[0];
        \assert($envelope instanceof Event);

        self::assertSame($sentMessage, $envelope->getMessage());
        self::assertTrue($envelope->hasProperty(Property::RETRY_COUNT));
        self::assertSame("1", $envelope->getProperty(Property::RETRY_COUNT));
        self::assertTrue($envelope->hasProperty(Property::RETRY_DELAI));
        self::assertTrue($envelope->hasProperty(Property::RETRY_MAX));
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

                        public function get(): ?MessageEnvelope
                        {
                            throw new \BadMethodCallException("We are not testing this.");
                        }

                        public function dispatch(MessageEnvelope $envelope): void
                        {
                            throw new \BadMethodCallException("We are not testing this.");
                        }

                        public function ack(MessageEnvelope $envelope): void
                        {
                            throw new \BadMethodCallException("We are not testing this.");
                        }

                        public function reject(MessageEnvelope $envelope, ?\Throwable $exception = null): void
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
