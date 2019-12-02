<?php

declare(strict_types=1);

namespace Goat\Domain\Event;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class DefaultDispatcher extends AbstractDispatcher
{
    private $appBus;
    private $asyncBus;

    /**
     * Default constructor
     */
    public function __construct(MessageBusInterface $appBus, MessageBusInterface $asyncBus)
    {
        $this->appBus = $appBus;
        $this->asyncBus = $asyncBus;
    }

    /**
     * Process
     */
    protected function doSynchronousProcess(MessageEnvelope $envelope): Envelope
    {
        return $this->appBus->dispatch($envelope->getMessage());
    }

    /**
     * Send in bus
     */
    protected function doAsynchronousDispatch(MessageEnvelope $envelope): Envelope
    {
        return $this->asyncBus->dispatch($envelope->getMessage());
    }
}
