<?php

declare(strict_types=1);

namespace Goat\Domain\Event;

use Goat\Domain\MessageBroker\MessageBroker;
use Symfony\Component\Messenger\Handler\HandlersLocatorInterface;

final class DefaultDispatcher extends AbstractDirectDispatcher
{
    private MessageBroker $messageBroker;

    /**
     * Default constructor
     */
    public function __construct(HandlersLocatorInterface $handlersLocator, MessageBroker $messageBroker)
    {
        parent::__construct($handlersLocator);

        $this->messageBroker = $messageBroker;
    }

    /**
     * {@inheritdoc}
     */
    protected function doRequeue(MessageEnvelope $envelope): void
    {
        $this->messageBroker->reject($envelope);
    }

    /**
     * {@inheritdoc}
     */
    protected function doReject(MessageEnvelope $envelope): void
    {
        $this->messageBroker->reject($envelope);
    }

    /**
     * {@inheritdoc}
     */
    protected function doAsynchronousCommandDispatch(MessageEnvelope $envelope): void
    {
        $this->messageBroker->dispatch($envelope);
    }

    /**
     * {@inheritdoc}
     */
    protected function doAsynchronousEventDispatch(MessageEnvelope $envelope): void
    {
        $this->messageBroker->dispatch($envelope);
    }
}
