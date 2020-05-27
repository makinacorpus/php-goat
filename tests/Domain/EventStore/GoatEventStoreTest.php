<?php

declare(strict_types=1);

namespace Goat\Domain\Tests\EventStore;

use Goat\Domain\EventStore\Event;
use Goat\Runner\Testing\TestDriverFactory;

final class GoatDispatcherTest extends AbstractEventStoreTest
{
    /**
     * @dataProvider runnerDataProvider
     */
    public function testStorePopulateEventData(TestDriverFactory $factory)
    {
        $runner = $factory->getRunner();

        $store = $this->createEventStore($runner, $factory->getSchema());

        $store->store($message = new MockMessage1(7, "booh", null), null, 'some_type', false);

        $stream = $store->query()->failed(null)->execute();
        $this->assertSame(1, \count($stream));

        foreach ($stream as $event) {
            \assert($event instanceof Event);

            $this->assertInstanceOf(Event::class, $event);
            $this->assertFalse($event->hasFailed());
            $this->assertNotNull($event->getAggregateId());
            $this->assertSame(MockMessage1::class, $event->getName());
            $this->assertSame('some_type', $event->getAggregateType());

            $this->assertSame('application/json', $event->getMessageContentType());
            $this->assertSame(MockMessage1::class, $event->getProperty(Event::PROP_MESSAGE_TYPE));

            $loadedMessage = $event->getMessage();
            \assert($loadedMessage instanceof MockMessage1);

            $this->assertSame($message->foo, $loadedMessage->foo);
            $this->assertSame($message->getBar(), $loadedMessage->getBar());
            $this->assertSame($message->getBaz(), $loadedMessage->getBaz());
        }
    }

    /**
     * @dataProvider runnerDataProvider
     */
    public function testStorePropagatesAggregateRoot(TestDriverFactory $factory)
    {
        $runner = $factory->getRunner();

        $store = $this->createEventStore($runner, $factory->getSchema());

        $store->store(new MockMessage2('foo', 'bar', $aggregateRootId = $this->createUuid()));
        $store->store($message = new MockMessage2('foo', 'baz', $this->createUuid(), $aggregateRootId));

        $stream = $store->query()->for($message->getAggregateId())->failed(null)->execute();
        $this->assertSame(1, \count($stream));

        foreach ($stream as $event) {
            \assert($event instanceof Event);

            $this->assertTrue($message->getAggregateId()->equals($event->getAggregateId()));
            $this->assertTrue($aggregateRootId->equals($event->getAggregateRoot()));
        }
    }
}
