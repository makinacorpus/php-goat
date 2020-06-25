<?php

declare(strict_types=1);

namespace Goat\Domain\Tests\Projector\Runtime;

use Goat\Domain\EventStore\Event;
use Goat\Domain\Projector\ProjectorRegistry;
use Goat\Domain\Projector\Projector\BrokenProjector;
use Goat\Domain\Projector\Projector\CallbackProjector;
use Goat\Domain\Projector\Runtime\DefaultRuntimePlayer;
use Goat\Domain\Projector\State\ArrayStateStore;
use PHPUnit\Framework\TestCase;

class DefaultRuntimeTest extends TestCase
{
    public function testEmptyPlayDoesNothing(): void
    {
        $registry = new ProjectorRegistry();
        $registry->setProjectors([]);

        $player = new DefaultRuntimePlayer(
            $registry,
            new ArrayStateStore()
        );

        self::expectNotToPerformAssertions();
        $player->dispatch($this->createEventAt(new \DateTimeImmutable(), 3));
    }

    public function testErrorDoesNotBlockOthers(): void
    {
        $registry = new ProjectorRegistry();
        $registry->setProjectors([
            $fooProjector = new CallbackProjector('foo', static function () {
                throw new \DomainException("I shall not fail.");
            }),
            $barProjector = new BrokenProjector('bar'),
        ]);

        $stateStore = new ArrayStateStore();

        $player = new DefaultRuntimePlayer(
            $registry,
            $stateStore
        );

        $player->dispatch($this->createEventAt(new \DateTimeImmutable(), 4));

        self::assertSame(1, $fooProjector->getOnEventCallCount());
        self::assertSame(1, $barProjector->getOnEventCallCount());

        $fooState = $stateStore->latest('foo');
        self::assertTrue($fooState->isError());
        self::assertSame(4, $fooState->getLatestEventPosition());

        $barState = $stateStore->latest('bar');
        self::assertFalse($barState->isError());
        self::assertSame(4, $barState->getLatestEventPosition());
    }

    public function alreadyPlayedEventsDoesNotReplay(): void
    {
        $registry = new ProjectorRegistry();
        $registry->setProjectors([
            $fooProjector = new BrokenProjector('foo'),
            $barProjector = new BrokenProjector('bar'),
        ]);

        $stateStore = new ArrayStateStore();
        $stateStore->update('bar', $this->createEventAt(new \DateTimeImmutable(), 7));

        $player = new DefaultRuntimePlayer(
            $registry,
            $stateStore
        );

        $player->dispatch($this->createEventAt(new \DateTimeImmutable(), 4));

        self::assertSame(1, $fooProjector->getOnEventCallCount());
        self::assertSame(0, $barProjector->getOnEventCallCount());

        $fooState = $stateStore->latest('foo');
        self::assertFalse($fooState->isError());
        self::assertSame(4, $fooState->getLatestEventPosition());

        $barState = $stateStore->latest('bar');
        self::assertFalse($barState->isError());
        self::assertSame(7, $barState->getLatestEventPosition());
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
}
