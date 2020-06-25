<?php

declare(strict_types=1);

namespace Goat\Domain\Projector\Worker;

use Goat\Domain\EventStore\Event;
use Goat\Domain\EventStore\EventStore;
use Goat\Domain\Projector\Projector;
use Goat\Domain\Projector\ProjectorNotReplyableError;
use Goat\Domain\Projector\ProjectorRegistry;
use Goat\Domain\Projector\ReplayableProjector;
use Goat\Domain\Projector\State\ProjectorLockedError;
use Goat\Domain\Projector\State\State;
use Goat\Domain\Projector\State\StateStore;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Default (and propably only) implementation.
 *
 * Interface exists for the need of decorating the worker.
 *
 * @todo Instrument using psr/log.
 */
final class DefaultWorker implements Worker
{
    private ProjectorRegistry $projectorRegistry;
    private EventStore $eventStore;
    private StateStore $stateStore;
    private ?EventDispatcherInterface $eventDispatcher = null;

    public function __construct(
        ProjectorRegistry $projectorRegistry,
        EventStore $eventStore,
        StateStore $stateStore
    ) {
        $this->projectorRegistry = $projectorRegistry;
        $this->eventStore = $eventStore;
        $this->stateStore = $stateStore;
    }

    /**
     * {@inheritdoc}
     */
    public function getEventDispatcher(): EventDispatcherInterface
    {
        return $this->eventDispatcher ?? ($this->eventDispatcher = new EventDispatcher());
    }

    /**
     * {@inheritdoc}
     */
    public function play(string $id, bool $reset = false, bool $continueOnError = false): void
    {
        $this->doPlay([$this->getProjector($id)], null, $continueOnError);
    }

    /**
     * {@inheritdoc}
     */
    public function playFrom(string $id, \DateTimeInterface $from, bool $continueOnError = false): void
    {
        $this->doPlay([$this->getProjector($id)], $from, $continueOnError);
    }

    /**
     * {@inheritdoc}
     */
    public function playAll(bool $continueOnError = false): void
    {
        $this->doPlay($this->getAllProjectors(), null, $continueOnError);
    }

    /**
     * {@inheritdoc}
     */
    public function playAllFrom(\DateTimeInterface $from, bool $continueOnError = false): void
    {
        $this->doPlay($this->getAllProjectors(), $from, $continueOnError);
    }

    /**
     * {@inheritdoc}
     */
    public function reset(string $id): void
    {
        $projector = $this->projectorRegistry->find($id);

        if ($projector instanceof ReplayableProjector) {
            $projector->reset();
        } else {
            throw new ProjectorNotReplyableError($id);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function resetAll(): void
    {
        foreach ($this->getAllProjectors() as $projector) {
            if ($projector instanceof ReplayableProjector) {
                $projector->reset();
            }
        }
    }

    /**
     * @param Projector[] $projectors
     */
    private function doPlay(iterable $projectors, ?\DateTimeInterface $from, bool $continueOnError): void
    {
        $states = $this->mapProjectors($projectors, $continueOnError);

        if ($date = $this->findLowestDateFromProjectorList($states, $from)) {
            $stream = $this->eventStore->query()->fromDate($date)->execute();
        } else {
            $stream = $this->eventStore->query()->execute();
        }

        $streamSize = $stream->count();
        $currentIndex = 0;

        $this->dispatch(WorkerEvent::begin($streamSize));

        if ($streamSize <= 0) {
            $this->dispatch(WorkerEvent::end($streamSize));

            return;
        }

        foreach ($stream as $event) {
            \assert($event instanceof Event);

            $this->dispatch(WorkerEvent::next($streamSize, ++$currentIndex));

            $atLeastOne = false;

            foreach ($states as $id => $projector) {
                \assert($projector instanceof ProjectorState);

                try {
                    if ($projector->stopped) {
                        continue;
                    }

                    if ($projector->position < $event->getPosition()) {
                        $projector->instance->onEvent($event);
                        $projector->lastEvent = $event;
                    }

                    $atLeastOne = true;

                } catch (\Throwable $e) {
                    $projector->stopped = true;

                    $state = $this->stateStore->exception($id, $event, $e, true);
                    $this->dispatch(WorkerEvent::error($streamSize, $currentIndex, $state));
                }
            }

            if (!$atLeastOne) {
                $this->dispatch(WorkerEvent::broken($streamSize, $currentIndex));

                break;
            }
        }

        // Finally update all projectors states.
        foreach ($states as $id => $projector) {
            \assert($projector instanceof ProjectorState);

            if ($projector->lastEvent) {
                $this->stateStore->update($id, $projector->lastEvent, true);
            }
        }

        $this->dispatch(WorkerEvent::end($streamSize, $currentIndex));
    }

    /**
     * @param array<string, ProjectorState> $projectors
     *
     * @return null|\DateTimeInterface
     *   Returning null means projectors with no date exist.
     */
    private function findLowestDateFromProjectorList(array $projectors, ?\DateTimeInterface $minDate): ?\DateTimeInterface
    {
        foreach ($projectors as $projector) {
            \assert($projector instanceof ProjectorState);

            if ($projector->position < 1) {
                return null;
            }

            $projectorDate = $projector->state->getLatestEventDate();

            if (!$minDate) {
                $minDate = $projectorDate;
            } else if ($projectorDate < $minDate) {
                $minDate = $projectorDate;
            }
        }

        return $minDate;
    }

    /**
     * Dispatch event if listeners are attached.
     */
    private function dispatch(WorkerEvent $event): void
    {
        if ($this->eventDispatcher) {
            $this->eventDispatcher->dispatch($event, $event->getEventName());
        }
    }

    /**
     * @param Projector[] $projectors
     *
     * @return array<string, ProjectorState>
     */
    private function mapProjectors(iterable $projectors, bool $continueOnError): array
    {
        $ret = [];

        foreach ($projectors as $projector) {
            \assert($projector instanceof Projector);

            $id = $projector->getIdentifier();

            try {
                $state = $this->stateStore->lock($id);

                if ($continueOnError || !$state->isError()) {
                    $ret[$id] = new ProjectorState($projector, $state);
                }
            } catch (ProjectorLockedError $e) {
                // Do nothing here.
                // @todo Instrumentation: log that.
            }
        }

        if (empty($ret)) {
            throw new MissingProjectorError("All projectors are in error or locked, cannot continue."); 
        }

        return $ret;
    }

    /**
     * @return Projector[]
     */
    private function getAllProjectors(): iterable
    {
        $ret = $this->projectorRegistry->getAll();

        if (empty($ret)) {
            throw new MissingProjectorError("There is no projectors, cannot continue.");
        }

        return $ret;
    }

    private function getProjector(string $id): Projector
    {
        return $this->projectorRegistry->find($id);
    }
}

/**
 * @internal
 */
final class ProjectorState
{
    public Projector $instance;
    public State $state;
    public int $position;
    public ?Event $lastEvent = null;
    public bool $stopped = false;

    public function __construct(Projector $projector, ?State $state)
    {
        $this->instance = $projector;
        $this->state = $state ?? State::empty($projector->getIdentifier());
        $this->position = $this->state->getLatestEventPosition();
    }
}
