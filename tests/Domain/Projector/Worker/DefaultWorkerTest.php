<?php

declare(strict_types=1);

namespace Goat\Domain\Tests\Projector\Worker;

use Goat\Domain\Projector\ProjectorDoesNotExistError;
use Goat\Domain\Projector\ProjectorRegistry;
use Goat\Domain\Projector\Projector\BrokenProjector;
use Goat\Domain\Projector\Projector\CallbackProjector;
use Goat\Domain\Projector\State\ArrayStateStore;
use Goat\Domain\Projector\Worker\DefaultWorker;
use Goat\Domain\Projector\Worker\MissingProjectorError;
use Goat\Domain\Projector\Worker\WorkerEvent;
use Goat\EventStore\AbstractEventQuery;
use Goat\EventStore\AbstractEventStore;
use Goat\EventStore\Event;
use Goat\EventStore\EventQuery;
use Goat\EventStore\EventStore;
use Goat\EventStore\EventStream;
use Goat\EventStore\Testing\DummyArrayEventStream;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\UuidInterface;

class DefaultWorkerTest extends TestCase
{
    public function testEmptyRegistryRaiseError(): void
    {
        $registry = new ProjectorRegistry();
        $registry->setProjectors([]);

        $worker = new DefaultWorker(
            $registry,
            $this->createNullEventStore(),
            new ArrayStateStore()
        );

        self::expectException(MissingProjectorError::class);
        $worker->playAll();
    }

    public function testNonExistingProjectorRaiseError(): void
    {
        $registry = new ProjectorRegistry();
        $registry->setProjectors([
            new BrokenProjector('foo'),
        ]);

        $worker = new DefaultWorker(
            $registry,
            $this->createNullEventStore(),
            new ArrayStateStore()
        );

        self::expectException(ProjectorDoesNotExistError::class);
        $worker->play('bar');
    }

    public function testEmptyEventStreamRaiseBeginAndEndEvent(): void
    {
        $registry = new ProjectorRegistry();
        $registry->setProjectors([
            new BrokenProjector('foo'),
        ]);

        $worker = new DefaultWorker(
            $registry,
            $this->createNullEventStore(),
            new ArrayStateStore()
        );

        $beginCount = 0;
        $endCount = 0;

        $dispatcher = $worker->getEventDispatcher();
        $dispatcher->addListener(WorkerEvent::BEGIN, static function () use (&$beginCount) {
            ++$beginCount;
        });
        $dispatcher->addListener(WorkerEvent::END, static function () use (&$endCount) {
            ++$endCount;
        });

        $dispatcher->addListener(WorkerEvent::NEXT, static function () {
            throw new \BadMethodCallException();
        });
        $dispatcher->addListener(WorkerEvent::ERROR, static function () {
            throw new \BadMethodCallException();
        });
        $dispatcher->addListener(WorkerEvent::BROKEN, static function () {
            throw new \BadMethodCallException();
        });

        $worker->playAll();

        self::assertSame(1, $beginCount);
        self::assertSame(1, $endCount);
    }

    public function testLockedProjectorDoesNotPlay(): void
    {
        $registry = new ProjectorRegistry();
        $registry->setProjectors([
            $fooProjector = new BrokenProjector('foo'),
            $barProjector = new BrokenProjector('bar'),
        ]);

        $stateStore = new ArrayStateStore();

        $worker = new DefaultWorker(
            $registry,
            $this->createNullEventStore([
                $this->createEventAt(new \DateTimeImmutable(), 1),
                $this->createEventAt(new \DateTimeImmutable(), 2),
                $this->createEventAt(new \DateTimeImmutable(), 3),
            ]),
            $stateStore
        );

        $stateStore->lock('bar'); 

        $worker->playAll();

        self::assertSame(3, $fooProjector->getOnEventCallCount());
        self::assertSame(0, $barProjector->getOnEventCallCount());

        $stateFoo = $stateStore->latest('foo');
        self::assertSame(3, $stateFoo->getLatestEventPosition());
        self::assertFalse($stateFoo->isLocked());

        $stateBar = $stateStore->latest('bar');
        self::assertsame(0, $stateBar->getLatestEventPosition());
        self::assertTrue($stateBar->isLocked());
    }

    public function testErroneousProjectorDoesNotPlay(): void
    {
        $registry = new ProjectorRegistry();
        $registry->setProjectors([
            $fooProjector = new BrokenProjector('foo'),
            $barProjector = new BrokenProjector('bar'),
        ]);

        $stateStore = new ArrayStateStore();

        $worker = new DefaultWorker(
            $registry,
            $this->createNullEventStore([
                $this->createEventAt(new \DateTimeImmutable(), 1),
                $this->createEventAt(new \DateTimeImmutable(), 2),
                $this->createEventAt(new \DateTimeImmutable(), 3),
            ]),
            $stateStore
        );

        $stateStore->error(
            'bar',
            $this->createEventAt(new \DateTimeImmutable(), 1),
            'Foo error'
        );

        $worker->playAll();

        self::assertSame(3, $fooProjector->getOnEventCallCount());
        self::assertSame(0, $barProjector->getOnEventCallCount());

        $stateFoo = $stateStore->latest('foo');
        self::assertSame(3, $stateFoo->getLatestEventPosition());
        self::assertFalse($stateFoo->isLocked());

        $stateBar = $stateStore->latest('bar');
        self::assertsame(1, $stateBar->getLatestEventPosition());
        self::assertTrue($stateBar->isError());
    }

    public function testErroneousProjectorDoesPlayWhenContinueOnError(): void
    {
        $registry = new ProjectorRegistry();
        $registry->setProjectors([
            $fooProjector = new BrokenProjector('foo'),
            $barProjector = new BrokenProjector('bar'),
        ]);

        $stateStore = new ArrayStateStore();

        $worker = new DefaultWorker(
            $registry,
            $this->createNullEventStore([
                $this->createEventAt(new \DateTimeImmutable(), 1),
                $this->createEventAt(new \DateTimeImmutable(), 2),
                $this->createEventAt(new \DateTimeImmutable(), 3),
            ]),
            $stateStore
        );

        $stateStore->error(
            'bar',
            $this->createEventAt(new \DateTimeImmutable(), 1),
            'Foo error'
        );

        $worker->playAll(true);

        self::assertSame(3, $fooProjector->getOnEventCallCount());
        self::assertSame(2, $barProjector->getOnEventCallCount());

        $stateFoo = $stateStore->latest('foo');
        self::assertSame(3, $stateFoo->getLatestEventPosition());
        self::assertFalse($stateFoo->isLocked());

        $stateBar = $stateStore->latest('bar');
        self::assertsame(3, $stateBar->getLatestEventPosition());
        self::assertFalse($stateBar->isError());
    }

    public function testWhenErrorRaisedProjectorIsStopped(): void
    {
        $registry = new ProjectorRegistry();
        $registry->setProjectors([
            $fooProjector = new BrokenProjector('foo'),
            $barProjector = new CallbackProjector('bar', static function (Event $event): void {
                throw new \DomainException("Something really bad happened.");
            }),
        ]);

        $stateStore = new ArrayStateStore();

        $worker = new DefaultWorker(
            $registry,
            $this->createNullEventStore([
                $this->createEventAt(new \DateTimeImmutable(), 1),
                $this->createEventAt(new \DateTimeImmutable(), 2),
                $this->createEventAt(new \DateTimeImmutable(), 3),
            ]),
            $stateStore
        );

        $errorCount = 0;

        $dispatcher = $worker->getEventDispatcher();
        $dispatcher->addListener(WorkerEvent::ERROR, static function () use (&$errorCount) {
            ++$errorCount;
        });

        $worker->playAll();

        self::assertSame(3, $fooProjector->getOnEventCallCount());
        self::assertSame(1, $barProjector->getOnEventCallCount());

        self::assertSame(1, $errorCount);

        $stateFoo = $stateStore->latest('foo');
        self::assertFalse($stateFoo->isError());
        self::assertFalse($stateFoo->isLocked());
        self::assertSame(3, $stateFoo->getLatestEventPosition());
        self::assertNull($stateFoo->getErrorMessage());

        $stateBar = $stateStore->latest('bar');
        self::assertTrue($stateBar->isError());
        self::assertFalse($stateBar->isLocked());
        self::assertSame(1, $stateBar->getLatestEventPosition());
        self::assertSame('Something really bad happened.', $stateBar->getErrorMessage());
    }

    public function testAlreadyAdvancedProjectorsAreNotPlayedAgain(): void
    {
        $registry = new ProjectorRegistry();
        $registry->setProjectors([
            $fooProjector = new BrokenProjector('foo'),
            $barProjector = new BrokenProjector('bar'),
        ]);

        $stateStore = new ArrayStateStore();

        $worker = new DefaultWorker(
            $registry,
            $this->createNullEventStore([
                $this->createEventAt(new \DateTimeImmutable(), 1),
                $this->createEventAt(new \DateTimeImmutable(), 2),
                $this->createEventAt(new \DateTimeImmutable(), 3),
            ]),
            $stateStore
        );

        $stateStore->update(
            'foo',
            $this->createEventAt(new \DateTimeImmutable(), 2)
        );

        $stateStore->update(
            'bar',
            $this->createEventAt(new \DateTimeImmutable(), 1)
        );

        $worker->playAll(true);

        self::assertSame(1, $fooProjector->getOnEventCallCount());
        self::assertSame(2, $barProjector->getOnEventCallCount());

        $stateFoo = $stateStore->latest('foo');
        self::assertSame(3, $stateFoo->getLatestEventPosition());

        $stateBar = $stateStore->latest('bar');
        self::assertsame(3, $stateBar->getLatestEventPosition());
    }

    public function testDateMagic(): void
    {
        self::markTestIncomplete("Implement me.");
    }

    private function createEventAt($message, int $position, ?\DateTimeInterface $validAt = null): Event
    {
        $event = Event::create(new \DateTimeImmutable());

        $func = \Closure::bind(
            static function (Event $event) use ($position, $validAt) {
                $event->position = $position;
                $event->validAt = $validAt ?? new \DateTimeImmutable();
            },
            null,
            Event::class
        );

        $func($event);

        return $event;
    }

    private function createNullEventStore(array $events = []): EventStore
    {
        return new class ($events) extends AbstractEventStore
        {
            private array $events;

            public function __construct(array $events)
            {
                $this->events = $events;
            }

            /**
             * {@inheritdoc}
             */
            public function findByPosition(int $position): Event
            {
                throw new \Exception("Not implemented.");
            }

            /**
             * {@inheritdoc}
             */
            public function query(): EventQuery
            {
                return new class ($this->events) extends AbstractEventQuery
                {
                    private array $events;

                    public function __construct(array $events)
                    {
                        $this->events = $events;
                    }

                    /**
                     * {@inheritdoc}
                     */
                    public function execute(): EventStream
                    {
                        return new DummyArrayEventStream($this->events);
                    }
                };
            }

            /**
             * {@inheritdoc}
             */
            public function count(EventQuery $query): ?int
            {
                throw new \Exception("Not implemented.");
            }

            /**
             * {@inheritdoc}
             */
            public function findByRevision(UuidInterface $aggregateId, int $revision): Event
            {
                throw new \Exception("Not implemented.");
            }

            /**
             * {@inheritdoc}
             */
            protected function doMoveAt(Event $event, int $newRevision): Event
            {
                throw new \Exception("Not implemented.");
            }

            /**
             * {@inheritdoc}
             */
            protected function doStore(Event $event): Event
            {
                throw new \Exception("Not implemented.");
            }

            /**
             * {@inheritdoc}
             */
            protected function doUpdate(Event $event): Event
            {
                throw new \Exception("Not implemented.");
            }
        };
    }
}
