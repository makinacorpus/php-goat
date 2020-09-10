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
        $id = ++self::$commandCount;
        try {
            $this->logger->debug("Dispatcher BEGIN ({id}) DISPATCH message", ['id' => $id, 'message' => $message, 'properties' => $properties]);
            $this->doDispatch(MessageEnvelope::wrap($message, $properties));
        } finally {
            $this->logger->debug("Dispatcher END ({id}) DISPATCH message", ['id' => $id]);
        }
    }

    /**
     * {@inheritdoc}
     */
    final public function process($message, array $properties = []): void
    {
        $id = ++self::$commandCount;
        try {
            $this->logger->debug("Dispatcher BEGIN ({id}) PROCESS message", ['id' => $id, 'message' => $message, 'properties' => $properties]);
            $this->doProcess(MessageEnvelope::wrap($message, $properties));
        } finally {
            $this->logger->debug("Dispatcher END ({id}) PROCESS message", ['id' => $id]);
        }
    }

    /**
     * Process message synchronously.
     */
    private function doProcess(MessageEnvelope $envelope): void
    {
        $message = $envelope->getMessage();

        ($this->handlerLocator->find($message))($message);
    }

    /**
     * Dispatch message to message broker.
     */
    private function doDispatch(MessageEnvelope $envelope): void
    {
        $this->messageBroker->dispatch($envelope);
    }
}
