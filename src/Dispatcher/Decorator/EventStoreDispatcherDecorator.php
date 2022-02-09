<?php

declare(strict_types=1);

namespace Goat\Dispatcher\Decorator;

use Goat\Dispatcher\Dispatcher;
use MakinaCorpus\EventStore\EventStore;
use MakinaCorpus\EventStore\Projector\Runtime\RuntimePlayer;
use MakinaCorpus\Message\Envelope;

final class EventStoreDispatcherDecorator implements Dispatcher
{
    private Dispatcher $decorated;
    private EventStore $eventStore;
    private ?RuntimePlayer $runtimePlayer = null;

    public function __construct(
        Dispatcher $decorated,
        EventStore $eventStore,
        ?RuntimePlayer $runtimePlayer = null
    ) {
        $this->decorated = $decorated;
        $this->eventStore = $eventStore;
        $this->runtimePlayer = $runtimePlayer;
    }

    /**
     * Synchronous process means we are doing the business transaction.
     *
     * At this point, event must be created and stored.
     *
     * {@inheritdoc}
     */
    public function process($message, array $properties = []): void
    {
        $envelope = Envelope::wrap($message, $properties);

        $event = $this
            ->eventStore
            ->append($envelope->getMessage())
            ->properties($envelope->getProperties())
            ->execute()
        ;

        try {
            $this->decorated->process($envelope);

            // Updating the event is necessary because other decorators might
            // have added meta-data in the event properties, we do not want to
            // loose that.
            $event = $this
                ->eventStore
                ->update($event)
                ->properties($envelope->getProperties())
                ->execute()
            ;

            if ($this->runtimePlayer) {
                $this->runtimePlayer->dispatch($event);
            }
        } catch (\Throwable $e) {
            $this
                ->eventStore
                ->failedWith($event, $e)
                ->properties($envelope->getProperties())
                ->execute()
            ;

            throw $e;
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
}
