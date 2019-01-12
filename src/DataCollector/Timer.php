<?php

declare(strict_types=1);

namespace Goat\Bridge\Symfony\DataCollector;

/**
 * Basic timer
 *
 * @codeCoverageIgnore
 */
final class Timer
{
    private $start;
    private $duration;

    /**
     * Build and start the timer
     */
    public function __construct()
    {
        $this->start = \microtime(true);
    }

    /**
     * Get duration in milliseconds
     *
     * If the timer is currently still started, it returns the current
     * duration and continues
     */
    public function getDuration(): int
    {
        if ($this->duration) {
            return $this->duration;
        }

        return (int)\round((\microtime(true) - $this->start) * 1000);
    }

    /**
     * Is the current timer running
     */
    public function isRunning(): bool
    {
        return null !== $this->duration;
    }

    /**
     * Stop the timer and return the duration in milliseconds
     */
    public function stop(): int
    {
        return $this->duration = (int)\round((\microtime(true) - $this->start) * 1000);
    }
}
