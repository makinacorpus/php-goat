<?php

declare(strict_types=1);

namespace Goat\Domain\Projector\Projector;

use Goat\Domain\EventStore\Event;
use Goat\Domain\Projector\Projector;

/**
 * Null object pattern implementation.
 */
final class BrokenProjector implements Projector
{
    private string $id;
    private int $onEventCount = 0;

    public function __construct(string $id)
    {
        $this->id = $id;
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
