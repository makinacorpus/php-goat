<?php

declare(strict_types=1);

namespace Goat\Domain\Tests\EventStore;

use Goat\Domain\EventStore\Event;
use Goat\Runner\Runner;

final class GoatDispatcherTest extends AbstractEventStoreTest
{
    /**
     * @dataProvider getRunners
     */
    public function testStorePopulateEventData(Runner $runner)
    {
        $store = $this->createEventStore($runner);

        $store->store($message = new MockMessage1(7, "booh", null), null, 'some_type', false);

        $stream = $store->query()->failed(null)->execute();
        $this->assertSame(1, \count($stream));

        /** @var \Goat\Domain\EventStore\Event $event */
        foreach ($stream as $event) {
            $this->assertInstanceOf(Event::class, $event);
            $this->assertFalse($event->hasFailed());
            $this->assertNotNull($event->getAggregateId());
            $this->assertSame(MockMessage1::class, $event->getName());
            $this->assertSame('some_type', $event->getAggregateType());

            $this->assertSame('application/json', $event->getMessageContentType());
            $this->assertSame(MockMessage1::class, $event->getProperty(Event::PROP_MESSAGE_TYPE));

            /** @var \Goat\Domain\Tests\Event\Store\MockMessage1 $loadedMessage */
            $loadedMessage = $event->getMessage();
            $this->assertSame($message->foo, $loadedMessage->foo);
            $this->assertSame($message->getBar(), $loadedMessage->getBar());
            $this->assertSame($message->getBaz(), $loadedMessage->getBaz());
        }
    }

    /**
     * @dataProvider getRunners
     */
    public function testStorePropagatesAggregateRoot(Runner $runner)
    {
        $store = $this->createEventStore($runner);

        $store->store(new MockMessage2('foo', 'bar', $aggregateRootId = $this->createUuid()));
        $store->store($message = new MockMessage2('foo', 'baz', $this->createUuid(), $aggregateRootId));

        $stream = $store->query()->for($message->getAggregateId())->failed(null)->execute();
        $this->assertSame(1, \count($stream));

        /** @var \Goat\Domain\EventStore\Event $event */
        foreach ($stream as $event) {
            $this->assertTrue($message->getAggregateId()->equals($event->getAggregateId()));
            $this->assertTrue($aggregateRootId->equals($event->getAggregateRoot()));
        }
    }
}
