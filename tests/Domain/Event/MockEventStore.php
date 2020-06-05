<?php

declare(strict_types=1);

namespace Goat\Domain\Tests\Event;

use Goat\Domain\EventStore\AbstractEventStore;
use Goat\Domain\EventStore\Event;
use Goat\Domain\EventStore\EventQuery;

class MockEventStore extends AbstractEventStore
{
    private int $position = 0;
    /** @var Event[] */
    private array $stored = [];

    /** @return Event[] */
    public function getStored(): array
    {
        return $this->stored;
    }

    public function countStored(): int
    {
        return \count($this->stored);
    }

    /**
     * {@inheritdoc}
     */
    protected function doStore(Event $event): Event
    {
        $position = ++$this->position;

        $callback = \Closure::bind(
            static function (Event $event) use ($position): Event {
                $event = clone $event;
                $event->position = $position;
                return $event;
            },
            null, Event::class
        );

        return $this->stored[] = $callback($event);
    }

    /**
     * {@inheritdoc}
     */
    protected function doUpdate(Event $event): Event
    {
        foreach ($this->stored as $index => $candidate) {
            if ($event->getPosition() === $candidate->getPosition()) {
                $this->stored[$index] = $event;
                break;
            }
        }

        return $event;
    }

    /**
     * {@inheritdoc}
     */
    protected function doMoveAt(Event $event, int $newRevision): Event
    {
        throw new \Exception("This is not implemented yet.");
    }

    /**
     * {@inheritdoc}
     */
    public function query(): EventQuery
    {
        throw new \BadMethodCallException();
    }

    /**
     * {@inheritdoc}
     */
    public function count(EventQuery $query): ?int
    {
        throw new \BadMethodCallException();
    }
}
