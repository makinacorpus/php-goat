<?php

declare(strict_types=1);

namespace Goat\Dispatcher\Tests;

use Goat\Dispatcher\AbstractDispatcher;
use Goat\Dispatcher\MessageEnvelope;

class MockDispatcher extends AbstractDispatcher
{
    /** @var callable */
    private $asyncProcessCallback;
    /** @var callable */
    private $processCallback;
    /** @var null|callable */
    private $requeueCallback;

    /**
     * Default constructor
     */
    public function __construct(
        callable $processCallback,
        callable $asyncProcessCallback,
        ?callable $requeueCallback = null
    ) {
        parent::__construct();

        $this->asyncProcessCallback = $asyncProcessCallback;
        $this->processCallback = $processCallback;
        $this->requeueCallback = $requeueCallback;
    }

    public function setProcessCallback(callable $processCallback): void
    {
        $this->processCallback = $processCallback;
    }

    /**
     * Requeue message if possible.
     *
     * Envelope contains all retry-related properties.
     */
    protected function doRequeue(MessageEnvelope $envelope): void
    {
        if ($this->requeueCallback) {
            \call_user_func($this->requeueCallback, $envelope);
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function doSynchronousProcess(MessageEnvelope $envelope): void
    {
        \call_user_func($this->processCallback, $envelope);
    }

    /**
     * {@inheritdoc}
     */
    protected function doAsynchronousCommandDispatch(MessageEnvelope $envelope): void
    {
        \call_user_func($this->asyncProcessCallback, $envelope);
    }
}
