<?php

declare(strict_types=1);

namespace Goat\Domain\EventStore\Goat;

use Goat\Domain\EventStore\Event;
use Goat\Domain\EventStore\EventStream;
use Goat\Runner\ResultIterator;

final class GoatEventStream implements \IteratorAggregate, EventStream
{
    private ResultIterator $result;
    private GoatEventStore $store;

    public function __construct(ResultIterator $result, GoatEventStore $store)
    {
        $this->result = $result;
        $this->store = $store;
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
            yield $this->store->hydrateEvent($row);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function fetch(): ?Event
    {
        if ($row = $this->result->fetch()) {
            return $this->store->hydrateEvent($row);
        }
        return null;
    }
}
