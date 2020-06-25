<?php

declare(strict_types=1);

namespace Goat\Domain\Projector\Projector;

use Goat\Domain\EventStore\Event;
use Goat\Domain\Projector\Projector;

/**
 * Write a projector that uses a callback.
 */
class CallbackProjector implements Projector
{
    private string $id;
    private int $onEventCount = 0;
    /** @var callable */
    private $callback;

    public function __construct(string $id, callable $callback)
    {
        $this->id = $id;
        $this->callback = $callback;
    }

    /**
     * {@inheritdoc}
     */
    public function getIdentifier(): string
    {
        return $this->id;
    }

    /**
     * {@inheritdoc}
     */
    public function onEvent(Event $event): void
    {
        ++$this->onEventCount;

        ($this->callback)($event);
    }

    /**
     * {@inheritdoc}
     */
    public function getLastProcessedEventDate(): ?\DateTimeInterface
    {
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function getHandledEvents(): ?array
    {
        return null;
    }

    public function getOnEventCallCount(): int
    {
        return $this->onEventCount;
    }
}
