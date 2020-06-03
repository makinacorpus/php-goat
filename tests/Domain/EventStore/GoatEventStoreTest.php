<?php

declare(strict_types=1);

namespace Goat\Domain\Tests\EventStore;

use Goat\Domain\EventStore\Event;
use Goat\Domain\EventStore\Property;
use Goat\Runner\Testing\TestDriverFactory;

final class GoatEventStoreTest extends AbstractEventStoreTest
{
    /**
     * @dataProvider runnerDataProvider
     */
    public function testStorePopulateEventData(TestDriverFactory $factory): void
    {
        $runner = $factory->getRunner();

        $store = $this->createEventStore($runner, $factory->getSchema());

        $store->store($message = new MockMessage1(7, "booh", null), null, 'some_type', false);

        $stream = $store->query()->failed(null)->execute();
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

        $store = $this->createEventStore($runner, $factory->getSchema());

        $store->store(new MockMessage2('foo', 'bar', $aggregateRootId = $this->createUuid()));
        $store->store($message = new MockMessage2('foo', 'baz', $this->createUuid(), $aggregateRootId));

        $stream = $store->query()->for($message->getAggregateId())->failed(null)->execute();
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

        $store = $this->createEventStore($runner, $factory->getSchema());

        $store->store(new MockMessage1(7, "booh", null), null, 'some_type', false);
        $event2 = $store->store(new MockMessage1(11, "baah", null), null, 'some_other_type', false);

        $store->update($event2, [
            'x-test-property' => 11,
        ]);

        $stream = $store->query()->failed(null)->withType('some_type')->execute();
        self::assertSame(1, \count($stream));

        foreach ($stream as $event) {
            \assert($event instanceof Event);

            self::assertFalse($event->hasProperty('x-test-property'));
        }

        $stream = $store->query()->failed(null)->withType('some_other_type')->execute();
        self::assertSame(1, \count($stream));

        foreach ($stream as $event) {
            \assert($event instanceof Event);

            self::assertSame('11', $event->getProperty('x-test-property'));
        }
    }
}
