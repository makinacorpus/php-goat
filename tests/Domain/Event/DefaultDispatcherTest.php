<?php

declare(strict_types=1);

namespace Goat\Domain\Tests\Event;

use Goat\Domain\Event\MessageEnvelope;
use Goat\Domain\Event\Decorator\EventStoreDispatcherDecorator;
use Goat\Domain\Event\Error\DispatcherError;
use Goat\Domain\Event\Error\DispatcherRetryableError;
use Goat\Domain\Tests\EventStore\AbstractEventStoreTest;
use Goat\EventStore\Event;
use Goat\EventStore\Property;

final class DefaultDispatcherTest extends AbstractEventStoreTest
{
    public function testProcessSuccessStoresEvent(): void
    {
        $decorated = new MockDispatcher(
            static function () { /* No error means success. */ },
            static function () { throw new \BadMethodCallException(); }
        );

        $eventStore = new MockEventStore();
        $dispatcher = new EventStoreDispatcherDecorator($decorated, $eventStore);

        $dispatcher->process(new MockMessage());

        self::assertSame(1, $eventStore->countStored());

        $dispatcher->process(new MockMessage());

        self::assertSame(2, $eventStore->countStored());

        foreach ($eventStore->getStored() as $event) {
            self::assertFalse($event->hasFailed());
            self::assertNull($event->getErrorCode());
            self::assertNull($event->getErrorMessage());
            self::assertNull($event->getErrorTrace());
        }
    }

    public function testProcessFailureStoresEvent(): void
    {
        $decorated = new MockDispatcher(
            static function () { throw new \DomainException(); },
            static function () { throw new \BadMethodCallException(); }
        );

        $eventStore = new MockEventStore();
        $dispatcher = new EventStoreDispatcherDecorator($decorated, $eventStore);

        try {
            $dispatcher->process(new MockMessage());
            self::fail();
        } catch (DispatcherError $e) {
        }

        self::assertSame(1, $eventStore->countStored());

        try {
            $dispatcher->process(new MockMessage());
            self::fail();
        } catch (DispatcherError $e) {
        }

        self::assertSame(2, $eventStore->countStored());

        foreach ($eventStore->getStored() as $event) {
            self::assertTrue($event->hasFailed());
            self::assertNotNull($event->getErrorCode());
            self::assertNotNull($event->getErrorMessage());
            self::assertNotNull($event->getErrorTrace());
        }
    }

    public function testProcessNestedStoresAllEventsInOrder(): void
    {
        $count = 0;

        $decorated = new MockDispatcher(
            static function () { throw new \DomainException(); },
            static function () { throw new \BadMethodCallException(); }
        );

        $eventStore = new MockEventStore();
        $dispatcher = new EventStoreDispatcherDecorator($decorated, $eventStore);

        $decorated->setProcessCallback(
            static function () use ($dispatcher, &$count) {
                if (++$count < 3) {
                    $dispatcher->process(
                        MessageEnvelope::wrap(
                            new MockMessage(),
                            [
                                'x-test-count' => $count,
                            ]
                        )
                    );
                }
            }
        );

        $dispatcher->process(new MockMessage());

        self::assertSame(3, $eventStore->countStored());

        $stored = $eventStore->getStored();
        self::assertNull($stored[0]->getProperty('x-test-count'));
        self::assertSame('1', $stored[1]->getProperty('x-test-count'));
        self::assertSame('2', $stored[2]->getProperty('x-test-count'));
    }

    public function testProcessConvertExceptionToDispatcherError(): void
    {
        $dispatcher = new MockDispatcher(
            static function () { throw new \DomainException(); },
            static function () { throw new \BadMethodCallException(); }
        );

        try {
            $dispatcher->process(new MockMessage());
            self::fail();
        } catch (DispatcherError $e) {
            self::assertInstanceOf(\DomainException::class, $e->getPrevious());
        }
    }

    public function testProcessDoesNotAttemptRetryOnArbitraryException(): void
    {
        // Test is done by the non-throwing of exception.
        self::expectNotToPerformAssertions();

        $dispatcher = new MockDispatcher(
            static function () { throw new \DomainException(); },
            static function () { throw new \BadMethodCallException(); },
            static function (MessageEnvelope $envelope) {
               throw new \BadMethodCallException("This should not have been called.");
            }
        );

        try {
            $dispatcher->process(new MockMessage());
            self::fail();
        } catch (DispatcherError $e) {}
    }

    public function testProcessAttempsRetryOnRetryableError(): void
    {
        $retries = [];

        $dispatcher = new MockDispatcher(
            static function () { throw new DispatcherRetryableError(); },
            static function () { throw new \BadMethodCallException(); },
            static function (MessageEnvelope $envelope) use (&$retries) {
                $retries[] = $envelope;
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
            static function () { throw new \BadMethodCallException(); },
            static function (MessageEnvelope $envelope) use (&$retries) {
                $retries[] = $envelope;
            }
        );

        $sentMessage = new MockRetryableMessage();

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

    public function testProcessDoesNotAttemptRetryWhenMaxReached(): void
    {
        $IWasHere = false;

        $dispatcher = new MockDispatcher(
            static function () { throw new \DomainException(); },
            static function () { throw new \BadMethodCallException(); },
            static function (MessageEnvelope $envelope) use (&$IWasHere) {
               $IWasHere = true;
            }
        );

        $sentEnvelope = MessageEnvelope::wrap(new MockRetryableMessage(), [
            Property::RETRY_MAX => 4,
            Property::RETRY_COUNT => 4,
        ]);

        try {
            $dispatcher->process($sentEnvelope);
            self::fail();
        } catch (DispatcherError $e) {}

        self::assertFalse($IWasHere);
    }

    public function testProcessStoreFailedEventWhenRetry(): void
    {
        $decorated = new MockDispatcher(
            static function () { throw new \DomainException(); },
            static function () { throw new \BadMethodCallException(); },
            static function (MessageEnvelope $envelope) {
                // It should pass here. That's not what we test.
            }
        );

        $eventStore = new MockEventStore();
        $dispatcher = new EventStoreDispatcherDecorator($decorated, $eventStore);

        $sentMessage = new MockRetryableMessage();

        try {
            $dispatcher->process($sentMessage);
            self::fail();
        } catch (DispatcherRetryableError $e) {}


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
}
