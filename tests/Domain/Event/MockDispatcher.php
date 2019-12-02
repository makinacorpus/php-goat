<?php

declare(strict_types=1);

namespace Goat\Domain\Tests\Event;

use Goat\Domain\Event\AbstractDispatcher;
use Goat\Domain\Event\MessageEnvelope;
use Symfony\Component\Messenger\Envelope;

class MockDispatcher extends AbstractDispatcher
{
    private $asyncProcessCallback;
    private $processCallback;

    /**
     * Default constructor
     */
    public function __construct(callable $processCallback, callable $asyncProcessCallback)
    {
        $this->asyncProcessCallback = $asyncProcessCallback;
        $this->processCallback = $processCallback;
    }

    /**
     * {@inheritdoc}
     */
    protected function doSynchronousProcess(MessageEnvelope $envelope): Envelope
    {
        return \call_user_func($this->processCallback, $envelope);
    }

    /**
     * {@inheritdoc}
     */
    protected function doAsynchronousDispatch(MessageEnvelope $envelope): Envelope
    {
        return \call_user_func($this->asyncProcessCallback, $envelope);
    }
}
