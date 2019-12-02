<?php

declare(strict_types=1);

namespace Goat\Domain\Event;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\NoHandlerForMessageException;
use Symfony\Component\Messenger\Handler\HandlersLocatorInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * Dispatches messages on AMQP.
 */
abstract class AbstractDirectDispatcher extends AbstractDispatcher
{
    /** @var HandlersLocatorInterface */
    private $handlersLocator;

    /**
     * Default constructor
     */
    public function __construct(HandlersLocatorInterface $handlersLocator)
    {
        $this->handlersLocator = $handlersLocator;
    }

    /**
     * {@inheritdoc}
     *
     * Directly uses the handlers locator instead of going throught the bus
     * since we handle transaction and logging ourself, there's no need to
     * go thought the whole messenger chain.
     */
    protected function doSynchronousProcess(MessageEnvelope $envelope): void
    {
        $symfonyEnvelope = new Envelope($message = $envelope->getMessage());

        $handlers = $this->handlersLocator->getHandlers($symfonyEnvelope);

        foreach ($handlers as $alias => $handler) {
            $symfonyEnvelope = $symfonyEnvelope->with(HandledStamp::fromCallable(
                $handler, $handler($message),
                \is_string($alias) ? $alias : null
            ));
        }

        if (null === $handler) {
            throw new NoHandlerForMessageException(\sprintf(
                'No handler for message "%s".',
                \get_class($message)
            ));
        }
    }
}
