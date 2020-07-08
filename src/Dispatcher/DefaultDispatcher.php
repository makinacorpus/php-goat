<?php

declare(strict_types=1);

namespace Goat\Dispatcher;

use Goat\MessageBroker\MessageBroker;

final class DefaultDispatcher extends AbstractDirectDispatcher
{
    private MessageBroker $messageBroker;

    /**
     * Default constructor
     */
    public function __construct(HandlerLocator $handlerLocator, MessageBroker $messageBroker)
    {
        parent::__construct($handlerLocator);

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
}
