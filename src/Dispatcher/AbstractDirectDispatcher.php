<?php

declare(strict_types=1);

namespace Goat\Dispatcher;


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
    private HandlerLocator $handlesLocator;

    public function __construct(HandlerLocator $handlerLocator)
    {
        parent::__construct();

        $this->handlesLocator = $handlerLocator;
    }

    /**
     * {@inheritdoc}
     */
    protected function doSynchronousProcess(MessageEnvelope $envelope): void
    {
        $message = $envelope->getMessage();

        ($this->handlesLocator->find($message))($message);
    }
}
