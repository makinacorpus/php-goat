<?php

declare(strict_types=1);

namespace Goat\Domain\Tests\Event;

use Goat\Domain\Event\MessageEnvelope;
use Goat\Domain\EventStore\Event;
use Goat\Domain\Tests\EventStore\AbstractEventStoreTest;
use Goat\Runner\Runner;
use Symfony\Component\Messenger\Envelope;

final class GoatDispatcherTest extends AbstractEventStoreTest
{
    /**
     * @dataProvider getRunners
     */
    public function testStoreOnSuccessInTransaction(Runner $runner)
    {
        $eventStore = $this->createEventStore($runner);

        $dispatcher = new MockDispatcher(
            function (MessageEnvelope $message) {
                return new Envelope($message);
            },
            function () {
                throw new \Exception("This should be processed synchronously");
            }
        );
        $dispatcher->setEventStore($eventStore);

        $id = $this->createUuid();
        $this->assertNull($this->findLastEventOf($eventStore, $id));

        $dispatcher->process(new MockMessage($id));

        $this->assertCount(1, $this->findEventOf($eventStore, $id));
        $this->assertInstanceOf(Event::class, $event = $this->findLastEventOf($eventStore, $id));
        $this->assertFalse($event->hasFailed());
        $this->assertNull($event->getErrorCode());
        $this->assertNull($event->getErrorMessage());
        $this->assertNull($event->getErrorTrace());
    }

    /**
     * @dataProvider getRunners
     */
    public function testStoreOnSuccess(Runner $runner)
    {
        $eventStore = $this->createEventStore($runner);

        $dispatcher = new MockDispatcher(
            function (MessageEnvelope $message) {
                return new Envelope($message);
            },
            function () {
                throw new \Exception("This should be processed synchronously");
            }
        );
        $dispatcher->setEventStore($eventStore);

        $id = $this->createUuid();
        $this->assertNull($this->findLastEventOf($eventStore, $id));

        $dispatcher->process(new MockMessage($id));

        $this->assertCount(1, $this->findEventOf($eventStore, $id));
        $this->assertInstanceOf(Event::class, $event = $this->findLastEventOf($eventStore, $id));
        $this->assertFalse($event->hasFailed());
        $this->assertNull($event->getErrorCode());
        $this->assertNull($event->getErrorMessage());
        $this->assertNull($event->getErrorTrace());
    }

    /**
     * @dataProvider getRunners
     */
    public function testStoreWithErrorInTransaction(Runner $runner)
    {
        $eventStore = $this->createEventStore($runner);

        $dispatcher = new MockDispatcher(
            function (MessageEnvelope $message) {
                throw new \Exception("This is a failure", 12);
            },
            function () {
                throw new \Exception("This should be processed synchronously");
            }
        );
        $dispatcher->setEventStore($eventStore);

        $id = $this->createUuid();
        $this->assertNull($this->findLastEventOf($eventStore, $id));

        try {
            $dispatcher->process(new MockMessage($id));
            $this->fail("Exceptions must be re-thrown");
        } catch (\Exception $e) {
            $this->assertCount(1, $this->findEventOf($eventStore, $id));
            $this->assertInstanceOf(Event::class, $event = $this->findLastEventOf($eventStore, $id));
            $this->assertTrue($event->hasFailed());
            $this->assertSame(12, $event->getErrorCode());
            $this->assertSame("This is a failure", $event->getErrorMessage());
            $this->assertNotEmpty($event->getErrorTrace());
        }
    }

    /**
     * @dataProvider getRunners
     */
    public function testStoreWithErrorInTransactionWithFailedRollback(Runner $runner)
    {
        $this->markTestIncomplete("One must mock the goat transaction handler");
    }

    /**
     * @dataProvider getRunners
     */
    public function testStoreWithError(Runner $runner)
    {
        $eventStore = $this->createEventStore($runner);

        $dispatcher = new MockDispatcher(
            function (MessageEnvelope $message) {
                throw new \Exception("This is a failure", 12);
            },
            function () {
                throw new \Exception("This should be processed synchronously");
            }
        );
        $dispatcher->setEventStore($eventStore);

        $id = $this->createUuid();
        $this->assertNull($this->findLastEventOf($eventStore, $id));

        try {
            $dispatcher->process(new MockMessage($id));
            $this->fail("Exceptions must be re-thrown");
        } catch (\Exception $e) {
            $this->assertCount(1, $this->findEventOf($eventStore, $id));
            $this->assertInstanceOf(Event::class, $event = $this->findLastEventOf($eventStore, $id));
            $this->assertTrue($event->hasFailed());
            $this->assertSame(12, $event->getErrorCode());
            $this->assertSame("This is a failure", $event->getErrorMessage());
            $this->assertNotEmpty($event->getErrorTrace());
        }
    }
}
