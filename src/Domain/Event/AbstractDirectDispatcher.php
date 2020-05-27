<?php

declare(strict_types=1);

namespace Goat\Domain\Event;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\NoHandlerForMessageException;
use Symfony\Component\Messenger\Handler\HandlerDescriptor;
use Symfony\Component\Messenger\Handler\HandlersLocatorInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * Directly uses the handlers locator instead of going throught the bus
 * since we handle transaction and logging ourself, there's no need to
 * go thought the whole messenger chain.
 *
 * So, this is mostly copy/pasted code, sorry. Real difference lies on the
 * fact that we do not catch exceptions and break processing directly, this
 * way it is both faster, and more consistent. We also let business exceptions
 * raise instead of specializing it, which allows business errors to get up
 * to the caller when using the sync bus.
 *
 * @todo we should still catch exceptions when dealing async with the worker
 *
 * @see \Symfony\Component\Messenger\Middleware\HandleMessageMiddleware
 */
abstract class AbstractDirectDispatcher extends AbstractDispatcher
{
    private HandlersLocatorInterface $handlersLocator;

    public function __construct(HandlersLocatorInterface $handlersLocator)
    {
        parent::__construct();

        $this->handlersLocator = $handlersLocator;
    }

    /**
     * {@inheritdoc}
     */
    protected function doSynchronousProcess(MessageEnvelope $envelope): void
    {
        $symfonyEnvelope = new Envelope($message = $envelope->getMessage());

        foreach ($this->handlersLocator->getHandlers($symfonyEnvelope) as $handlerDescriptor) {
            if ($this->messageHasAlreadyBeenHandled($symfonyEnvelope, $handlerDescriptor)) {
                continue;
            }
            $handler = $handlerDescriptor->getHandler();
            $handledStamp = HandledStamp::fromDescriptor($handlerDescriptor, $handler($message));
            $symfonyEnvelope = $symfonyEnvelope->with($handledStamp);
        }

        if (null === $handler) {
            throw new NoHandlerForMessageException(\sprintf('No handler for message "%s".', \get_class($message)));
        }
    }

    private function messageHasAlreadyBeenHandled(Envelope $envelope, HandlerDescriptor $handlerDescriptor): bool
    {
        $some = \array_filter(
            $envelope->all(HandledStamp::class),
            function (HandledStamp $stamp) use ($handlerDescriptor) {
                return $stamp->getHandlerName() === $handlerDescriptor->getName();
            }
        );

        return \count($some) > 0;
    }
}
