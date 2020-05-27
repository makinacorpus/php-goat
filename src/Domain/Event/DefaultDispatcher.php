<?php

declare(strict_types=1);

namespace Goat\Domain\Event;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Handler\HandlersLocatorInterface;

final class DefaultDispatcher extends AbstractDirectDispatcher
{
    private MessageBusInterface $asyncBus;
    private MessageBusInterface $eventBus;

    /**
     * Default constructor
     */
    public function __construct(
        HandlersLocatorInterface $handlersLocator,
        MessageBusInterface $asyncBus,
        ?MessageBusInterface $eventBus = null
    ) {
        parent::__construct($handlersLocator);

        $this->asyncBus = $asyncBus;
        $this->eventBus = $eventBus ?? $asyncBus;
    }

    /**
     * {@inheritdoc}
     */
    protected function doAsynchronousCommandDispatch(MessageEnvelope $envelope): void
    {
        $this->asyncBus->dispatch($envelope->getMessage());
    }

    /**
     * {@inheritdoc}
     */
    protected function doAsynchronousEventDispatch(MessageEnvelope $envelope): void
    {
        $this->eventBus->dispatch($envelope->getMessage());
    }
}
