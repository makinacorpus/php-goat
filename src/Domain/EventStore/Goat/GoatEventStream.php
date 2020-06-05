<?php

declare(strict_types=1);

namespace Goat\Domain\EventStore\Goat;

use Goat\Domain\EventStore\Event;
use Goat\Domain\EventStore\EventStream;
use Goat\Runner\ResultIterator;

final class GoatEventStream implements \IteratorAggregate, EventStream
{
    private ResultIterator $result;
    private GoatEventStore $eventStore;

    public function __construct(ResultIterator $result, GoatEventStore $eventStore)
    {
        $this->result = $result;
        $this->eventStore = $eventStore;
    }

    /**
     * {@inheritdoc}
     */
    public function count()
    {
        return $this->result->countRows();
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator()
    {
        foreach ($this->result as $row) {
            yield $this->eventStore->hydrateEvent($row);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function fetch(): ?Event
    {
        if ($row = $this->result->fetch()) {
            return $this->eventStore->hydrateEvent($row);
        }
        return null;
    }
}
