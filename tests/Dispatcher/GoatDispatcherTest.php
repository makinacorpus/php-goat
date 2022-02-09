<?php

declare(strict_types=1);

namespace Goat\Dispatcher\Tests;

use Goat\Dispatcher\Decorator\EventStoreDispatcherDecorator;
use Goat\Runner\Testing\TestDriverFactory;
use MakinaCorpus\EventStore\Event;
use MakinaCorpus\Message\Envelope;

final class GoatDispatcherTest extends AbstractWithEventStoreTest
{
    /**
     * @dataProvider runnerDataProvider
     */
    public function testStoreOnSuccessInTransaction(TestDriverFactory $factory)
    {
        $runner = $factory->getRunner();

        $eventStore = $this->createEventStore($runner, $factory->getSchema());

        $decorated = new MockDispatcher(
            function (Envelope $message) {
                return $message;
            },
            function () {
                throw new \Exception("This should be processed synchronously");
            }
        );
        $dispatcher = new EventStoreDispatcherDecorator($decorated, $eventStore);

        $id = $this->createUuid();
        self::assertNull($this->findLastEventOf($eventStore, $id));

        $dispatcher->process(new MockMessage($id));

        self::assertCount(1, $this->findEventOf($eventStore, $id));
        self::assertInstanceOf(Event::class, $event = $this->findLastEventOf($eventStore, $id));
        self::assertFalse($event->hasFailed());
        self::assertNull($event->getErrorCode());
        self::assertNull($event->getErrorMessage());
        self::assertNull($event->getErrorTrace());
    }

    /**
     * @dataProvider runnerDataProvider
     */
    public function testStoreOnSuccess(TestDriverFactory $factory)
    {
        $runner = $factory->getRunner();

        $eventStore = $this->createEventStore($runner, $factory->getSchema());

        $decorated = new MockDispatcher(
            function (Envelope $message) {
                return $message;
            },
            function () {
                throw new \Exception("This should be processed synchronously");
            }
        );
        $dispatcher = new EventStoreDispatcherDecorator($decorated, $eventStore);

        $id = $this->createUuid();
        self::assertNull($this->findLastEventOf($eventStore, $id));

        $dispatcher->process(new MockMessage($id));

        self::assertCount(1, $this->findEventOf($eventStore, $id));
        self::assertInstanceOf(Event::class, $event = $this->findLastEventOf($eventStore, $id));
        self::assertFalse($event->hasFailed());
        self::assertNull($event->getErrorCode());
        self::assertNull($event->getErrorMessage());
        self::assertNull($event->getErrorTrace());
    }

    /**
     * @dataProvider runnerDataProvider
     */
    public function testStoreWithErrorInTransaction(TestDriverFactory $factory)
    {
        $runner = $factory->getRunner();

        $eventStore = $this->createEventStore($runner, $factory->getSchema());

        $decorated = new MockDispatcher(
            function (Envelope $message) {
                throw new \DomainException("This is a failure", 12);
            },
            function () {
                throw new \Exception("This should be processed synchronously");
            }
        );
        $dispatcher = new EventStoreDispatcherDecorator($decorated, $eventStore);

        $id = $this->createUuid();
        self::assertNull($this->findLastEventOf($eventStore, $id));

        try {
            $dispatcher->process(new MockMessage($id));
            self::fail("Exceptions must be re-thrown");
        } catch (\DomainException $e) {
            self::assertCount(1, $this->findEventOf($eventStore, $id));
            self::assertInstanceOf(Event::class, $event = $this->findLastEventOf($eventStore, $id));
            self::assertTrue($event->hasFailed());
            self::assertSame(12, $event->getErrorCode());
            self::assertSame("This is a failure", $event->getErrorMessage());
            self::assertNotEmpty($event->getErrorTrace());
        }
    }

    /**
     * @dataProvider runnerDataProvider
     */
    public function testStoreWithError(TestDriverFactory $factory)
    {
        $runner = $factory->getRunner();

        $eventStore = $this->createEventStore($runner, $factory->getSchema());

        $decorated = new MockDispatcher(
            function (Envelope $message) {
                throw new \Exception("This is a failure", 12);
            },
            function () {
                throw new \Exception("This should be processed synchronously");
            }
        );
        $dispatcher = new EventStoreDispatcherDecorator($decorated, $eventStore);

        $id = $this->createUuid();
        self::assertNull($this->findLastEventOf($eventStore, $id));

        try {
            $dispatcher->process(new MockMessage($id));
            self::fail("Exceptions must be re-thrown");
        } catch (\Exception $e) {
            self::assertCount(1, $this->findEventOf($eventStore, $id));
            self::assertInstanceOf(Event::class, $event = $this->findLastEventOf($eventStore, $id));
            self::assertTrue($event->hasFailed());
            self::assertSame(12, $event->getErrorCode());
            self::assertSame("This is a failure", $event->getErrorMessage());
            self::assertNotEmpty($event->getErrorTrace());
        }
    }
}
