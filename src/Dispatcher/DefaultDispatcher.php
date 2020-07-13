<?php

declare(strict_types=1);

namespace Goat\Dispatcher;

use Goat\MessageBroker\MessageBroker;

final class DefaultDispatcher extends AbstractDispatcher
{
    private HandlerLocator $handlerLocator;
    private MessageBroker $messageBroker;

    public function __construct(HandlerLocator $handlerLocator, MessageBroker $messageBroker)
    {
        parent::__construct();

        $this->handlerLocator = $handlerLocator;
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
    protected function doSynchronousProcess(MessageEnvelope $envelope): void
    {
        $message = $envelope->getMessage();

        ($this->handlerLocator->find($message))($message);
    }

    /**
     * {@inheritdoc}
     */
    protected function doAsynchronousCommandDispatch(MessageEnvelope $envelope): void
    {
        $this->messageBroker->dispatch($envelope);
    }
}
