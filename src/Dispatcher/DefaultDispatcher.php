<?php

declare(strict_types=1);

namespace Goat\Dispatcher;

use Goat\MessageBroker\MessageBroker;

final class DefaultDispatcher implements Dispatcher
{
    private HandlerLocator $handlerLocator;
    private MessageBroker $messageBroker;

    public function __construct(HandlerLocator $handlerLocator, MessageBroker $messageBroker)
    {
        $this->handlerLocator = $handlerLocator;
        $this->messageBroker = $messageBroker;
    }

    /**
     * {@inheritdoc}
     */
    final public function dispatch($message, array $properties = []): void
    {
        $this->messageBroker->dispatch(MessageEnvelope::wrap($message, $properties));
    }

    /**
     * {@inheritdoc}
     */
    final public function process($message, array $properties = []): void
    {
        if ($message instanceof MessageEnvelope) {
            $message = $message->getMessage();
        }
        ($this->handlerLocator->find($message))($message);
    }
}
