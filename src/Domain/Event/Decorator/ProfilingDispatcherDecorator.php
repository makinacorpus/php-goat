<?php

declare(strict_types=1);

namespace Goat\Domain\Event\Decorator;

use Goat\Domain\Event\Dispatcher;
use Goat\Domain\Event\MessageEnvelope;
use Goat\EventStore\Property;

final class ProfilingDispatcherDecorator implements Dispatcher
{
    const PROP_TIME_START = 'x-goat-time-start';

    private Dispatcher $decorated;

    public function __construct(Dispatcher $decorated)
    {
        $this->decorated = $decorated;
    }

    /**
     * Synchronous process means we are doing the business transaction.
     *
     * {@inheritdoc}
     */
    public function process($message, array $properties = [], bool $withTransaction = true): void
    {
        $timerStart = \hrtime(true);
        $envelope = MessageEnvelope::wrap($message, $properties);

        try {
            $this->decorated->process($envelope, [], $withTransaction);
        } finally {
            $envelope->withProperties([
                Property::PROCESS_DURATION => self::nsecToMsec(\hrtime(true) - $timerStart) . ' ms',
            ]);
        }
    }

    /**
     * Dispatch means we are NOT processing the business transaction but
     * queuing it into the bus, do nothing.
     *
     * {@inheritdoc}
     */
    public function dispatch($message, array $properties = []): void
    {
        $this->decorated->dispatch($message, $properties);
    }

    /**
     * Convert nano seconds to milliseconds and round the result.
     */
    private static function nsecToMsec(float $nsec): int
    {
        return (int) ($nsec / 1e+6);
    }
}
