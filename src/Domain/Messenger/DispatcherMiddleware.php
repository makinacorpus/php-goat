<?php

declare(strict_types=1);

namespace Goat\Domain\Messenger;

use Goat\Domain\Event\Dispatcher;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;

/**
 * Middleware used when consuming messages, intercept messages from an external
 * bus and send it to the dispatcher for direct processing.
 */
final class DispatcherMiddleware implements MiddlewareInterface
{
    /** @var bool */
    private $async = false;

    /** @var Dispatcher */
    private $dispatcher;

    /**
     * Default constructor.
     */
    public function __construct(Dispatcher $dispatcher, bool $async = false)
    {
        $this->dispatcher = $dispatcher;
    }

    /**
     * {@inheritdoc}
     */
    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        if ($envelope->all(ReceivedStamp::class)) {
            // This evenvelope has been received, we are consuming the message.
            $this->dispatcher->process($envelope->getMessage());

            return $envelope;
        }

        return $stack->next()->handle($envelope, $stack);
    }
}
