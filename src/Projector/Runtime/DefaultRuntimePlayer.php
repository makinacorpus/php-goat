<?php

declare(strict_types=1);

namespace Goat\Projector\Runtime;

use Goat\EventStore\Event;
use Goat\Projector\Projector;
use Goat\Projector\ProjectorRegistry;
use Goat\Projector\State\StateStore;

/**
 * @todo Instrument using psr/log.
 */
final class DefaultRuntimePlayer implements RuntimePlayer
{
    private ProjectorRegistry $projectorRegistry;
    private StateStore $stateStore;

    public function __construct(ProjectorRegistry $projectorRegistry, StateStore $stateStore)
    {
        $this->projectorRegistry = $projectorRegistry;
        $this->stateStore = $stateStore;
    }

    /**
     * {@inheritdoc}
     */
    public function dispatch(Event $event): void
    {
        $projectors = $this->projectorRegistry->getAll();

        foreach ($projectors as $projector) {
            \assert($projector instanceof Projector);

            $id = $projector->getIdentifier();

            // @todo Optimize this by loading everything at once.
            $state = $this->stateStore->latest($id);

            try {
                if (!$state || $state->getLatestEventPosition() < $event->getPosition()) {
                    $projector->onEvent($event);

                    // @todo Optimize this by updating all at once.
                    $this->stateStore->update($id, $event, true);
                }
            } catch (\Throwable $e) {
                $state = $this->stateStore->exception($id, $event, $e, true);
            }
        }
    }
}
