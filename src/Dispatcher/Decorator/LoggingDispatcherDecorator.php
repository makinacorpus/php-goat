<?php

declare(strict_types=1);

namespace Goat\Dispatcher\Decorator;

use Goat\Dispatcher\Dispatcher;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;

/**
 * Add extra logging debug information.
 */
final class LoggingDispatcherDecorator implements Dispatcher, LoggerAwareInterface
{
    use LoggerAwareTrait;

    const PROP_TIME_START = 'x-goat-time-start';

    private static $messageCount = 0;

    private Dispatcher $decorated;

    public function __construct(Dispatcher $decorated)
    {
        $this->decorated = $decorated;
        $this->logger = new NullLogger();
    }

    /**
     * Synchronous process means we are doing the business transaction.
     *
     * {@inheritdoc}
     */
    public function process($message, array $properties = []): void
    {
        $id = ++self::$messageCount;
        try {
            $this->logger->debug("Dispatcher BEGIN ({id}) PROCESS message", ['id' => $id, 'message' => $message, 'properties' => $properties]);
            $this->decorated->process($message, $properties);
        } finally {
            $this->logger->debug("Dispatcher END ({id}) PROCESS message", ['id' => $id]);
        }
    }

    /**
     * Dispatch means we are NOT processing the business transaction but
     * queuing it into the bus, do nothing.
     *
     * {@inheritdoc}
     */
    public function dispatch($message, array $properties = []): void
    {
        $id = ++self::$messageCount;
        try {
            $this->logger->debug("Dispatcher BEGIN ({id}) DISPATCH message", ['id' => $id, 'message' => $message, 'properties' => $properties]);
            $this->decorated->dispatch($message, $properties);
        } finally {
            $this->logger->debug("Dispatcher END ({id}) DISPATCH message", ['id' => $id]);
        }
    }
}
