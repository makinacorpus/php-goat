<?php

declare(strict_types=1);

namespace Goat\Domain\Projector\Worker;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Projector player.
 */
interface Worker
{
    /**
     * Get internal event dispatcher.
     */
    public function getEventDispatcher(): EventDispatcherInterface;

    /**
     * Play event stream for a single projector.
     */
    public function play(string $id, bool $reset = false, bool $continueOnError = true): void;

    /**
     * Play event stream for a single projector from date.
     */
    public function playFrom(string $id, \DateTimeInterface $date, bool $continueOnError = true): void;

    /**
     * Play event stream for all projectors.
     */
    public function playAll(bool $continueOnError = true): void;

    /**
     * Play event stream for all projectors from date.
     */
    public function playAllFrom(\DateTimeInterface $date, bool $continueOnError = true): void;

    /**
     * Reset all data of a single projector.
     */
    public function reset(string $id): void;

    /**
     * Rest all data for all projectors.
     *
     * Warning: you should probably never call this.
     */
    public function resetAll(): void;
}
