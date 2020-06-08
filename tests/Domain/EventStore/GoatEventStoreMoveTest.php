<?php

declare(strict_types=1);

namespace Goat\Domain\Tests\EventStore;

use Goat\Domain\EventStore\Event;
use Goat\Domain\EventStore\EventStore;
use Goat\Domain\EventStore\Property;
use Goat\Domain\Tests\Event\MockMessage;
use Goat\Runner\Testing\TestDriverFactory;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class GoatEventStoreMoveTest extends AbstractEventStoreTest
{
    /**
     * Considering the following sequence: 1, 3.
     * And A inserts after 2.
     *
     * Result will be: 1, A, 3.
     *
     * @dataProvider runnerDataProvider
     */
    public function testInsertAfter1(TestDriverFactory $factory): void
    {
        self::markTestSkipped("OK, this doesn't work.");

        $runner = $factory->getRunner();

        $aggregateA = Uuid::uuid4();
        $eventStore = $this->createEventStore($runner, $factory->getSchema());

        $eventStore
            ->append(new MockMessage(), '1')
            ->aggregate('foo', $aggregateA)
            ->date(\DateTimeImmutable::createFromFormat('Y-m-d H:i:s', '2020-06-05 12:00:13'))
            ->execute()
        ;

        $eventStore
            ->append(new MockMessage(), '3')
            ->aggregate('foo', $aggregateA)
            ->date(\DateTimeImmutable::createFromFormat('Y-m-d H:i:s', '2020-06-05 12:00:14'))
            ->execute()
        ;

        self::assertStreamIs(
            $eventStore,
            $aggregateA,
            ['1', '3']
        );

        $newEvent = $eventStore
            ->insertAfter($aggregateA, 2, new MockMessage())
            ->name('A')
            ->aggregate('foo', $aggregateA)
            ->execute()
        ;

        self::assertTrue($newEvent->hasProperty(Property::MODIFIED_INSERTED));

        self::assertStreamIs(
            $eventStore,
            $aggregateA,
            ['1', 'A', '3']
        );
    }

    /**
     * Considering A, and the following sequence: 1, 2, 3.
     * And A inserts after 2.
     *
     * Result will be: 1, 2, A, 3.
     *
     * @dataProvider runnerDataProvider
     */
    public function testInsertAfter2(TestDriverFactory $factory): void
    {
        $runner = $factory->getRunner();

        $aggregateA = Uuid::uuid4();
        $eventStore = $this->createEventStore($runner, $factory->getSchema());

        $eventStore
            ->append(new MockMessage(), '1')
            ->aggregate('foo', $aggregateA)
            ->date(\DateTimeImmutable::createFromFormat('Y-m-d H:i:s', '2020-06-05 12:00:13'))
            ->execute()
        ;

        $eventStore
            ->append(new MockMessage(), '2')
            ->aggregate('foo', $aggregateA)
            ->date(\DateTimeImmutable::createFromFormat('Y-m-d H:i:s', '2020-06-05 12:00:14'))
            ->execute()
        ;

        $eventStore
            ->append(new MockMessage(), '3')
            ->aggregate('foo', $aggregateA)
            ->date(\DateTimeImmutable::createFromFormat('Y-m-d H:i:s', '2020-06-05 12:00:16'))
            ->execute()
        ;

        self::assertStreamIs(
            $eventStore,
            $aggregateA,
            ['1', '2', '3']
        );

        $newEvent = $eventStore
            ->insertAfter($aggregateA, 2, new MockMessage())
            ->name('A')
            ->aggregate('foo', $aggregateA)
            ->execute()
        ;

        self::assertTrue($newEvent->hasProperty(Property::MODIFIED_INSERTED));

        self::assertStreamIs(
            $eventStore,
            $aggregateA,
            ['1', '2', 'A', '3']
        );
    }

    /**
     * Considering A, and the following sequence: <empty>.
     * And A inserts after 2.
     *
     * Result will be: A.
     *
     * @dataProvider runnerDataProvider
     */
    public function testInsertAfter3(TestDriverFactory $factory): void
    {
        $runner = $factory->getRunner();

        $aggregateA = Uuid::uuid4();
        $eventStore = $this->createEventStore($runner, $factory->getSchema());

        self::assertStreamIs(
            $eventStore,
            $aggregateA,
            []
        );

        $newEvent = $eventStore
            ->insertAfter($aggregateA, 2, new MockMessage())
            ->name('A')
            ->aggregate('foo', $aggregateA)
            ->execute()
        ;

        self::assertTrue($newEvent->hasProperty(Property::MODIFIED_INSERTED));

        self::assertStreamIs(
            $eventStore,
            $aggregateA,
            ['A']
        );
    }

    /**
     * Considering the following sequence: A, 1, 3.
     * And A moves after 2.
     *
     * Result will be 1, A, 3.
     *
     * @dataProvider runnerDataProvider
     */
    public function testMoveAfter1(TestDriverFactory $factory): void
    {
        self::markTestSkipped("We need to be able to delete revision 2 for testing this.");

        $runner = $factory->getRunner();

        $aggregateA = Uuid::uuid4();
        $eventStore = $this->createEventStore($runner, $factory->getSchema());

        $event = $eventStore
            ->append(new MockMessage(), 'A')
            ->aggregate('foo', $aggregateA)
            ->date(\DateTimeImmutable::createFromFormat('Y-m-d H:i:s', '2020-06-05 12:00:12'))
            ->execute()
        ;

        $eventStore
            ->append(new MockMessage(), '1')
            ->aggregate('foo', $aggregateA)
            ->date(\DateTimeImmutable::createFromFormat('Y-m-d H:i:s', '2020-06-05 12:00:13'))
            ->execute()
        ;

        $eventStore
            ->append(new MockMessage(), '3')
            ->aggregate('foo', $aggregateA)
            ->date(\DateTimeImmutable::createFromFormat('Y-m-d H:i:s', '2020-06-05 12:00:14'))
            ->execute()
        ;

        self::assertStreamIs(
            $eventStore,
            $aggregateA,
            ['A', '1', '3']
        );

        $newEvent = $eventStore->moveAfterRevision($event, 2)->execute();

        self::assertNotSameDate($event->validAt(), $newEvent->validAt());
        self::assertTrue($newEvent->hasProperty(Property::MODIFIED_PREVIOUS_REVISION));
        self::assertTrue($newEvent->hasProperty(Property::MODIFIED_PREVIOUS_VALID_AT));

        self::assertStreamIs(
            $eventStore,
            $aggregateA,
            ['1', 'A', '3']
        );
    }

    /**
     * Considering the following sequence: A, 1, 2.
     * And A moves after 2.
     *
     * Result will be 1, 2, A.
     *
     * @dataProvider runnerDataProvider
     */
    public function testMoveAfter2(TestDriverFactory $factory): void
    {
        $runner = $factory->getRunner();

        $aggregateA = Uuid::uuid4();
        $eventStore = $this->createEventStore($runner, $factory->getSchema());

        $event = $eventStore
            ->append(new MockMessage(), 'A')
            ->aggregate('foo', $aggregateA)
            ->date(\DateTimeImmutable::createFromFormat('Y-m-d H:i:s', '2020-06-05 12:00:12'))
            ->execute()
        ;

        $eventStore
            ->append(new MockMessage(), '1')
            ->aggregate('foo', $aggregateA)
            ->date(\DateTimeImmutable::createFromFormat('Y-m-d H:i:s', '2020-06-05 12:00:13'))
            ->execute()
        ;

        $eventStore
            ->append(new MockMessage(), '2')
            ->aggregate('foo', $aggregateA)
            ->date(\DateTimeImmutable::createFromFormat('Y-m-d H:i:s', '2020-06-05 12:00:14'))
            ->execute()
        ;

        self::assertStreamIs(
            $eventStore,
            $aggregateA,
            ['A', '1', '2']
        );

        $newEvent = $eventStore->moveAfterRevision($event, 2)->execute();

        self::assertNotSameDate($event->validAt(), $newEvent->validAt());
        self::assertTrue($newEvent->hasProperty(Property::MODIFIED_PREVIOUS_REVISION));
        self::assertTrue($newEvent->hasProperty(Property::MODIFIED_PREVIOUS_VALID_AT));

        self::assertStreamIs(
            $eventStore,
            $aggregateA,
            ['1', 'A', '2']
        );
    }

    /**
     * Considering A, and the following sequence: 1, 2, A, 3.
     * And A moves after 2.
     *
     * Result will be the same. A will not be modified.
     *
     * @dataProvider runnerDataProvider
     */
    public function testMoveAfter3(TestDriverFactory $factory): void
    {
        $runner = $factory->getRunner();

        $aggregateA = Uuid::uuid4();
        $eventStore = $this->createEventStore($runner, $factory->getSchema());

        $eventStore
            ->append(new MockMessage(), '1')
            ->aggregate('foo', $aggregateA)
            ->date(\DateTimeImmutable::createFromFormat('Y-m-d H:i:s', '2020-06-05 12:00:13'))
            ->execute()
        ;

        $eventStore
            ->append(new MockMessage(), '2')
            ->aggregate('foo', $aggregateA)
            ->date(\DateTimeImmutable::createFromFormat('Y-m-d H:i:s', '2020-06-05 12:00:14'))
            ->execute()
        ;

        $event = $eventStore
            ->append(new MockMessage(), 'A')
            ->aggregate('foo', $aggregateA)
            ->date(\DateTimeImmutable::createFromFormat('Y-m-d H:i:s', '2020-06-05 12:00:15'))
            ->execute()
        ;

        $eventStore
            ->append(new MockMessage(), '3')
            ->aggregate('foo', $aggregateA)
            ->date(\DateTimeImmutable::createFromFormat('Y-m-d H:i:s', '2020-06-05 12:00:16'))
            ->execute()
        ;

        self::assertStreamIs(
            $eventStore,
            $aggregateA,
            ['1', '2', 'A', '3']
        );

        $newEvent = $eventStore->moveAfterRevision($event, 2)->execute();

        self::assertSameDate($event->validAt(), $newEvent->validAt());
        self::assertTrue($newEvent->hasProperty(Property::MODIFIED_PREVIOUS_REVISION));
        self::assertTrue($newEvent->hasProperty(Property::MODIFIED_PREVIOUS_VALID_AT));

        self::assertStreamIs(
            $eventStore,
            $aggregateA,
            ['1', '2', 'A', '3']
        );
    }

    /**
     * Considering A, and the following sequence: 1, 2, 3, A.
     * And A moves after 2.
     *
     * Result will be 1, 2, A, 3.
     *
     * @dataProvider runnerDataProvider
     */
    public function testMoveAfter4(TestDriverFactory $factory): void
    {
        $runner = $factory->getRunner();

        $aggregateA = Uuid::uuid4();
        $eventStore = $this->createEventStore($runner, $factory->getSchema());

        $eventStore
            ->append(new MockMessage(), '1')
            ->aggregate('foo', $aggregateA)
            ->date(\DateTimeImmutable::createFromFormat('Y-m-d H:i:s', '2020-06-05 12:00:13'))
            ->execute()
        ;

        $eventStore
            ->append(new MockMessage(), '2')
            ->aggregate('foo', $aggregateA)
            ->date(\DateTimeImmutable::createFromFormat('Y-m-d H:i:s', '2020-06-05 12:12:14'))
            ->execute()
        ;

        $eventStore
            ->append(new MockMessage(), '3')
            ->aggregate('foo', $aggregateA)
            ->date(\DateTimeImmutable::createFromFormat('Y-m-d H:i:s', '2020-06-05 12:20:16'))
            ->execute()
        ;

        $event = $eventStore
            ->append(new MockMessage(), 'A')
            ->aggregate('foo', $aggregateA)
            ->date(\DateTimeImmutable::createFromFormat('Y-m-d H:i:s', '2020-06-05 13:32:15'))
            ->execute()
        ;

        self::assertStreamIs(
            $eventStore,
            $aggregateA,
            ['1', '2', '3', 'A']
        );

        $newEvent = $eventStore->moveAfterRevision($event, 2)->execute();

        self::assertNotSameDate($event->validAt(), $newEvent->validAt());
        self::assertTrue($newEvent->hasProperty(Property::MODIFIED_PREVIOUS_REVISION));
        self::assertTrue($newEvent->hasProperty(Property::MODIFIED_PREVIOUS_VALID_AT));

        self::assertStreamIs(
            $eventStore,
            $aggregateA,
            ['1', '2', 'A', '3']
        );
    }

    /**
     * Considering A, and the following sequence: 1, A.
     * And A moves after 2.
     *
     * Result will be the same. A will not be modified.
     *
     * @dataProvider runnerDataProvider
     */
    public function testMoveAfter5(TestDriverFactory $factory): void
    {
        $runner = $factory->getRunner();

        $aggregateA = Uuid::uuid4();
        $eventStore = $this->createEventStore($runner, $factory->getSchema());

        $eventStore
            ->append(new MockMessage(), '1')
            ->aggregate('foo', $aggregateA)
            ->date(\DateTimeImmutable::createFromFormat('Y-m-d H:i:s', '2020-06-05 12:00:13'))
            ->execute()
        ;

        $event = $eventStore
            ->append(new MockMessage(), 'A')
            ->aggregate('foo', $aggregateA)
            ->date(\DateTimeImmutable::createFromFormat('Y-m-d H:i:s', '2020-06-05 13:32:15'))
            ->execute()
        ;

        self::assertStreamIs(
            $eventStore,
            $aggregateA,
            ['1', 'A']
        );

        $newEvent = $eventStore->moveAfterRevision($event, 2)->execute();

        self::assertSameDate($event->validAt(), $newEvent->validAt());
        self::assertTrue($newEvent->hasProperty(Property::MODIFIED_PREVIOUS_REVISION));
        self::assertTrue($newEvent->hasProperty(Property::MODIFIED_PREVIOUS_VALID_AT));

        self::assertStreamIs(
            $eventStore,
            $aggregateA,
            ['1', 'A']
        );
    }

    /**
     * Considering A, and the following sequence: A.
     * And A moves after 2.
     *
     * Result will be the same. A will not be modified.
     *
     * @dataProvider runnerDataProvider
     */
    public function testMoveAfter6(TestDriverFactory $factory): void
    {
        $runner = $factory->getRunner();

        $aggregateA = Uuid::uuid4();
        $eventStore = $this->createEventStore($runner, $factory->getSchema());

        $event = $eventStore
            ->append(new MockMessage(), 'A')
            ->aggregate('foo', $aggregateA)
            ->date(\DateTimeImmutable::createFromFormat('Y-m-d H:i:s', '2020-06-05 13:32:15'))
            ->execute()
        ;

        self::assertStreamIs(
            $eventStore,
            $aggregateA,
            ['A']
        );

        $newEvent = $eventStore->moveAfterRevision($event, 2)->execute();

        self::assertSameDate($event->validAt(), $newEvent->validAt());
        self::assertTrue($newEvent->hasProperty(Property::MODIFIED_PREVIOUS_REVISION));
        self::assertTrue($newEvent->hasProperty(Property::MODIFIED_PREVIOUS_VALID_AT));

        self::assertStreamIs(
            $eventStore,
            $aggregateA,
            ['A']
        );
    }

    private static function assertStreamIs(EventStore $eventStore, UuidInterface $aggregateId, array $expected)
    {
        $stream = \iterator_to_array(
            $eventStore
                ->query()
                ->for($aggregateId)
                ->execute()
        );

        $actual = \array_map(fn (Event $event) => $event->getName(), $stream);

        self::assertSame($expected, $actual);
    }

    private static function assertSameDate(\DateTimeInterface $expected, \DateTimeInterface $actual)
    {
        // W3C format includes msecs.
        self::assertSame(
            $expected->format(
                \DateTime::W3C
            ),
            $actual->format(
                \DateTime::W3C
            )
        );
    }

    private static function assertNotSameDate(\DateTimeInterface $expected, \DateTimeInterface $actual)
    {
        // W3C format includes msecs.
        self::assertNotSame(
            $expected->format(
                \DateTime::W3C
            ),
            $actual->format(
                \DateTime::W3C
            )
        );
    }
}
