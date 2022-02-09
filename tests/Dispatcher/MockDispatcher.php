<?php

declare(strict_types=1);

namespace Goat\Dispatcher\Tests;

use Goat\Dispatcher\Dispatcher;
use MakinaCorpus\Message\Envelope;

class MockDispatcher Implements Dispatcher
{
    /** @var callable */
    private $asyncProcessCallback;
    /** @var callable */
    private $processCallback;

    public function __construct(callable $processCallback, callable $asyncProcessCallback)
    {
        $this->asyncProcessCallback = $asyncProcessCallback;
        $this->processCallback = $processCallback;
    }

    public function setProcessCallback(callable $processCallback): void
    {
        $this->processCallback = $processCallback;
    }

    /**
     * {@inheritdoc}
     */
    public function process($message, array $properties = []): void
    {
        \call_user_func($this->processCallback, Envelope::wrap($message, $properties));
    }

    /**
     * {@inheritdoc}
     */
    public function dispatch($message, array $properties = []): void
    {
        \call_user_func($this->asyncProcessCallback, Envelope::wrap($message, $properties));
    }
}
