<?php

declare(strict_types=1);

namespace Goat\Domain\Tests\EventStore;

use Goat\Domain\EventStore\EventStore;
use Goat\Domain\Tests\Event\MockMessage;
use Goat\Runner\Testing\TestDriverFactory;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class GoatEventStoreMoveTest extends AbstractEventStoreTest
{
    /**
     * We will attempt to make it the most simple it can be, that will not
     * really be simple in the end, we need a stream in which:
     *
     *  - we can move an middle revision as first,
     *  - we can move an middle revision as last,
     *  - we can move an middle revision in the middle,
     *  - we can insert a revision as first,
     *  - we can insert a revision as last,
     *  - we can insert a revision in the middle,
     *
     * And we need another stream that will remain untouched, to unsure there
     * is no side effects in SQL queries.
     */
    private function populateScenario(EventStore $eventStore, UuidInterface $aggregateA, UuidInterface $aggregateB): void
    {
        // A
        $eventStore
            ->append(new MockMessage(), 'foo.create')
            ->aggregate('foo', $aggregateA)
            ->date(\DateTimeImmutable::createFromFormat('Y-m-d H:i:s', '2020-06-05 12:00:12'))
            ->execute()
        ;

        // B
        $eventStore
            ->append(new MockMessage(), 'foo.create')
            ->aggregate('foo', $aggregateB)
            ->date(\DateTimeImmutable::createFromFormat('Y-m-d H:i:s', '2020-06-05 12:00:12'))
            ->execute()
        ;

        // A
        $eventStore
            ->append(new MockMessage(), 'foo.update')
            ->aggregate('foo', $aggregateA)
            ->date(\DateTimeImmutable::createFromFormat('Y-m-d H:i:s', '2020-06-06 12:00:20'))
            ->execute()
        ;

        // A
        $eventStore
            ->append(new MockMessage(), 'foo.close')
            ->aggregate('foo', $aggregateA)
            ->date(\DateTimeImmutable::createFromFormat('Y-m-d H:i:s', '2020-06-07 12:00:21'))
            ->execute()
        ;

        // B
        $eventStore
            ->append(new MockMessage(), 'foo.close')
            ->aggregate('foo', $aggregateB)
            ->date(\DateTimeImmutable::createFromFormat('Y-m-d H:i:s', '2020-06-06 12:00:22'))
            ->execute()
        ;
    }

    private static function assertStreamIs(iterable $stream, array $reference)
    {
        if (!\is_array($stream)) {
            $stream = \iterator_to_array($stream);
        }

        foreach (\array_values($reference) as $index => $name) {
            self::assertSame($name, $stream[$index]->getName());
        }
    }

    /**
     * Just ensure that aggregate B was left untouched.
     */
    private static function assertAggregateBIsLeftUntouched(EventStore $eventStore, UuidInterface $aggregateB): void
    {
        self::assertStreamIs(
            $eventStore->query()->for($aggregateB)->execute(),
            [
                'foo.create',
                'foo.close',
            ]
        );
    }

    /**
     * @dataProvider runnerDataProvider
     */
    public function testMoveAfter(TestDriverFactory $factory): void
    {
        $runner = $factory->getRunner();

        $aggregateA = Uuid::uuid4();
        $aggregateB = Uuid::uuid4();

        $eventStore = $this->createEventStore($runner, $factory->getSchema());
        $this->populateScenario($eventStore, $aggregateA, $aggregateB);

        // Event we want to move.
        // foo.create is REV 1
        // foo.update is REV 2
        // foo.close is REV 3
        $event = $eventStore
            ->query()
            ->withName('foo.close')
            ->for($aggregateA)
            ->limit(1)
            ->execute()
            ->fetch()
        ;

        self::assertNotNull($event);

        $eventStore
            ->moveAfterRevision($event, 1)
            ->execute()
        ;

        // Now:
        // foo.create MUST be REV 1
        // foo.update MUST be REV 3
        // foo.close MUST be REV 2
        self::assertStreamIs(
            $eventStore->query()->for($aggregateA)->execute(),
            [
                'foo.create',
                'foo.close',
                'foo.update'
            ]
        );
    }

    /**
     * @dataProvider runnerDataProvider
     */
    public function testMoveAfterWhenNoneShouldBeLast(TestDriverFactory $factory): void
    {
        $runner = $factory->getRunner();

        $aggregateA = Uuid::uuid4();
        $aggregateB = Uuid::uuid4();

        $eventStore = $this->createEventStore($runner, $factory->getSchema());
        $this->populateScenario($eventStore, $aggregateA, $aggregateB);

        self::assertAggregateBIsLeftUntouched($eventStore, $aggregateB);
        self::markTestIncomplete();
    }

    /**
     * @dataProvider runnerDataProvider
     */
    public function testMoveAtDate(TestDriverFactory $factory): void
    {
        $runner = $factory->getRunner();

        $aggregateA = Uuid::uuid4();
        $aggregateB = Uuid::uuid4();

        $eventStore = $this->createEventStore($runner, $factory->getSchema());
        $this->populateScenario($eventStore, $aggregateA, $aggregateB);

        self::assertAggregateBIsLeftUntouched($eventStore, $aggregateB);
        self::markTestIncomplete();
    }

    /**
     * @dataProvider runnerDataProvider
     */
    public function testMoveAtDateWhenNoneShouldBeFirst(TestDriverFactory $factory): void
    {
        $runner = $factory->getRunner();

        $aggregateA = Uuid::uuid4();
        $aggregateB = Uuid::uuid4();

        $eventStore = $this->createEventStore($runner, $factory->getSchema());
        $this->populateScenario($eventStore, $aggregateA, $aggregateB);

        self::assertAggregateBIsLeftUntouched($eventStore, $aggregateB);
        self::markTestIncomplete();
    }

    /**
     * @dataProvider runnerDataProvider
     */
    public function testUpdate(TestDriverFactory $factory): void
    {
        $runner = $factory->getRunner();

        $aggregateA = Uuid::uuid4();
        $aggregateB = Uuid::uuid4();

        $eventStore = $this->createEventStore($runner, $factory->getSchema());
        $this->populateScenario($eventStore, $aggregateA, $aggregateB);

        self::assertAggregateBIsLeftUntouched($eventStore, $aggregateB);
        self::markTestIncomplete();
    }
}
