<?php

declare(strict_types=1);

namespace Goat\Domain\Tests\Event;

use Goat\Domain\EventStore\AbstractEventStore;
use Goat\Domain\EventStore\Event;
use Goat\Domain\EventStore\EventQuery;

class MockEventStore extends AbstractEventStore
{
    /** @var Event[] */
    private $stored = [];

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
        return $this->stored[] = $event;
    }

    /**

     */
    public function query(): EventQuery
    {
        throw new \BadMethodCallException();
    }

    public function count(EventQuery $query): ?int
    {
        throw new \BadMethodCallException();
    }
}
