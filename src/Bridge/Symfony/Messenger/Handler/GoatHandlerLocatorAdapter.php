<?php

declare(strict_types=1);

namespace Goat\Bridge\Symfony\Messenger\Handler;

use Goat\Dispatcher\HandlerLocator;
use Goat\Dispatcher\Error\HandlerNotFoundError;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\NoHandlerForMessageException;
use Symfony\Component\Messenger\Handler\HandlersLocatorInterface;
use Symfony\Component\Messenger\Handler\HandlerDescriptor;

final class GoatHandlerLocatorAdapter implements HandlerLocator 
{
    private HandlersLocatorInterface $handlersLocator;

    public function __construct(HandlersLocatorInterface $handlersLocator)
    {
        $this->handlersLocator = $handlersLocator;
    }

    /**
     * {@inheritdoc}
     */
    public function find($message): callable
    {
        $symfonyEnvelope = new Envelope($message);

        try {
            $descriptors = $this->handlersLocator->getHandlers($symfonyEnvelope);

            if ($descriptors) {
                foreach ($descriptors as $descriptor) {
                    \assert($descriptor instanceof HandlerDescriptor);

                    return $descriptor->getHandler();
                }
            }
        } catch (NoHandlerForMessageException $e) {
            throw new HandlerNotFoundError($e->getMessage(), 0, $e);
        }

        throw new NoHandlerForMessageException(\sprintf('No handler for message "%s".', \get_class($message)));
    }
}
