<?php

declare(strict_types=1);

namespace Goat\Dispatcher\Tests;

use Goat\Dispatcher\Dispatcher;
use Goat\Dispatcher\TransactionHandler;
use Goat\Dispatcher\Decorator\EventStoreDispatcherDecorator;
use Goat\Dispatcher\Decorator\LoggingDispatcherDecorator;
use Goat\Dispatcher\Decorator\ProfilingDispatcherDecorator;
use Goat\Dispatcher\Decorator\TransactionDispatcherDecorator;
use MakinaCorpus\EventStore\Testing\DummyArrayEventStore;
use MakinaCorpus\Message\Envelope;

final class DefaultDispatcherTest extends AbstractWithEventStoreTest
{
    public function testProcessSuccessStoresEvent(): void
    {
        $decorated = new MockDispatcher(
            static function () { /* No error means success. */ },
            static function () { throw new \BadMethodCallException(); }
        );

        $eventStore = new DummyArrayEventStore();
        $dispatcher = new EventStoreDispatcherDecorator($decorated, $eventStore);
        $dispatcher = $this->decorate($dispatcher);

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

        $eventStore = new DummyArrayEventStore();
        $dispatcher = new EventStoreDispatcherDecorator($decorated, $eventStore);
        $dispatcher = $this->decorate($dispatcher);

        try {
            $dispatcher->process(new MockMessage());
            self::fail();
        } catch (\DomainException $e) {
        }

        self::assertSame(1, $eventStore->countStored());

        try {
            $dispatcher->process(new MockMessage());
            self::fail();
        } catch (\DomainException $e) {
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

        $eventStore = new DummyArrayEventStore();
        $dispatcher = new EventStoreDispatcherDecorator($decorated, $eventStore);
        $dispatcher = $this->decorate($dispatcher);

        $decorated->setProcessCallback(
            static function () use ($dispatcher, &$count) {
                if (++$count < 3) {
                    $dispatcher->process(
                        Envelope::wrap(
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

    private function decorate(Dispatcher $decorated): Dispatcher
    {
        return new LoggingDispatcherDecorator(
            new ProfilingDispatcherDecorator(
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
                )
            )
        );
    }
}
