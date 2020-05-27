<?php

declare(strict_types=1);

namespace Goat\Domain\Tests\Event;

use Goat\Domain\Event\MessageEnvelope;
use Goat\Domain\EventStore\Event;
use Goat\Domain\Event\Error\DispatcherError;
use Goat\Domain\Event\Error\DispatcherRetryableError;
use Goat\Domain\Tests\EventStore\AbstractEventStoreTest;

final class DefaultDispatcherTest extends AbstractEventStoreTest
{
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
        self::assertSame("1", $envelope->getProperty(Event::PROP_RETRY_COUNT));
        self::assertSame("100", $envelope->getProperty(Event::PROP_RETRY_DELAI));
        self::assertSame("4", $envelope->getProperty(Event::PROP_RETRY_MAX));
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
        self::assertSame("1", $envelope->getProperty(Event::PROP_RETRY_COUNT));
        self::assertSame("100", $envelope->getProperty(Event::PROP_RETRY_DELAI));
        self::assertSame("4", $envelope->getProperty(Event::PROP_RETRY_MAX));
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
            Event::PROP_RETRY_MAX => 4,
            Event::PROP_RETRY_COUNT => 4,
        ]);

        try {
            $dispatcher->process($sentEnvelope);
            self::fail();
        } catch (DispatcherError $e) {}

        self::assertFalse($IWasHere);
    }

    public function testProcessStoreFailedEventWhenRetry(): void
    {
        $dispatcher = new MockDispatcher(
            static function () { throw new \DomainException(); },
            static function () { throw new \BadMethodCallException(); },
            static function (MessageEnvelope $envelope) {
                // It should pass here. That's not what we test.
            }
        );

        $eventStore = new MockEventStore();
        $dispatcher->setEventStore($eventStore);

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
        self::assertTrue($envelope->hasProperty(Event::PROP_RETRY_COUNT));
        self::assertSame("0", $envelope->getProperty(Event::PROP_RETRY_COUNT));
        self::assertFalse($envelope->hasProperty(Event::PROP_RETRY_DELAI));
        self::assertFalse($envelope->hasProperty(Event::PROP_RETRY_MAX));
    }
}
