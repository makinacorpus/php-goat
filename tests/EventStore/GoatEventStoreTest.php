<?php

declare(strict_types=1);

namespace Goat\EventStore\Tests;

use Goat\EventStore\Event;
use Goat\EventStore\Property;
use Goat\Runner\Testing\TestDriverFactory;

final class GoatEventStoreTest extends AbstractEventStoreTest
{
    /**
     * @dataProvider runnerDataProvider
     */
    public function testStorePopulateEventData(TestDriverFactory $factory): void
    {
        $runner = $factory->getRunner();

        $eventStore = $this->createEventStore($runner, $factory->getSchema());

        $message = new MockMessage1(7, "booh", null);
        $eventStore->append($message)->aggregate('some_type')->execute();

        $stream = $eventStore->query()->failed(null)->execute();
        self::assertSame(1, \count($stream));

        foreach ($stream as $event) {
            \assert($event instanceof Event);

            self::assertInstanceOf(Event::class, $event);
            self::assertFalse($event->hasFailed());
            self::assertNotNull($event->getAggregateId());
            self::assertSame(MockMessage1::class, $event->getName());
            self::assertSame('some_type', $event->getAggregateType());

            self::assertSame('application/json', $event->getMessageContentType());
            self::assertSame(MockMessage1::class, $event->getProperty(Property::MESSAGE_TYPE));

            $loadedMessage = $event->getMessage();
            \assert($loadedMessage instanceof MockMessage1);

            self::assertSame($message->foo, $loadedMessage->foo);
            self::assertSame($message->getBar(), $loadedMessage->getBar());
            self::assertSame($message->getBaz(), $loadedMessage->getBaz());
        }
    }

    /**
     * @dataProvider runnerDataProvider
     */
    public function testStorePropagatesAggregateRoot(TestDriverFactory $factory): void
    {
        $runner = $factory->getRunner();

        $eventStore = $this->createEventStore($runner, $factory->getSchema());

        $eventStore->append(new MockMessage2('foo', 'bar', $aggregateRootId = $this->createUuid()))->execute();
        $eventStore->append($message = new MockMessage2('foo', 'baz', $this->createUuid(), $aggregateRootId))->execute();

        $stream = $eventStore->query()->for($message->getAggregateId())->failed(null)->execute();
        self::assertSame(1, \count($stream));

        foreach ($stream as $event) {
            \assert($event instanceof Event);

            self::assertTrue($message->getAggregateId()->equals($event->getAggregateId()));
            self::assertTrue($aggregateRootId->equals($event->getAggregateRoot()));
        }
    }

    /**
     * @dataProvider runnerDataProvider
     */
    public function testUpdate(TestDriverFactory $factory): void
    {
        $runner = $factory->getRunner();

        $eventStore = $this->createEventStore($runner, $factory->getSchema());

        $eventStore->append(new MockMessage1(7, "booh", null))->aggregate('some_type')->execute();
        $event2 = $eventStore->append(new MockMessage1(11, "baah", null))->aggregate('some_other_type')->execute();

        $eventStore->update($event2)->property('x-test-property', '11')->execute();

        $stream = $eventStore->query()->failed(null)->withType('some_type')->execute();
        self::assertSame(1, \count($stream));

        foreach ($stream as $event) {
            \assert($event instanceof Event);

            self::assertFalse($event->hasProperty('x-test-property'));
        }

        $stream = $eventStore->query()->failed(null)->withType('some_other_type')->execute();
        self::assertSame(1, \count($stream));

        foreach ($stream as $event) {
            \assert($event instanceof Event);

            self::assertSame('11', $event->getProperty('x-test-property'));
        }
    }
}
