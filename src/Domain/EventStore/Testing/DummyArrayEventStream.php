<?php

declare(strict_types=1);

namespace Goat\Domain\EventStore\Testing;

use Goat\Domain\EventStore\Event;
use Goat\Domain\EventStore\EventStream;

/**
 * @var \Goat\Domain\Event\Event[]
 */
class DummyArrayEventStream implements EventStream, \Iterator
{
    /** @var Event[] */
    private array $events;
    private int $index = -1;
    private ?Event $current = null;

    /** @var Event */
    public function __construct(array $events)
    {
        $this->events = \array_values($events);

        $this->next();
    }

    /**
     * Fetch next in stream.
     *
     * Warning: iterating over this instance will advance in stream.
     */
    public function fetch(): ?Event
    {
        $this->next();

        return $this->current;
    }

    /**
     * {@inheritdoc}
     */
    public function count()
    {
        return \count($this->events);
    }

    /**
     * {@inheritdoc}
     */ 
    public function next()
    {
        $this->current = $this->events[++$this->index] ?? null;
    }

    /**
     * {@inheritdoc}
     */
    public function valid()
    {
        return null !== $this->current;
    }

    /**
     * {@inheritdoc}
     */
    public function current()
    {
        return $this->current;
    }

    /**
     * {@inheritdoc}
     */
    public function rewind()
    {
        $this->index = -1;
        $this->current = null;

        $this->next();
    }

    /**
     * {@inheritdoc}
     */
    public function key()
    {
        return 0 <= $this->index ? $this->index : null;
    }
}
