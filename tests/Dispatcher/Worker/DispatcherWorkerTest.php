<?php

declare(strict_types=1);

namespace Goat\Dispatcher\Tests\Worker;

use Goat\Dispatcher\Dispatcher;
use Goat\Dispatcher\MessageEnvelope;
use Goat\Dispatcher\Worker\Worker;
use Goat\Dispatcher\Worker\WorkerEvent;
use Goat\MessageBroker\MessageBroker;
use PHPUnit\Framework\TestCase;

final class DispatcherWorkerTest extends TestCase
{
    public function testIdleWillStop(): void
    {
        $dispatcher = new class implements Dispatcher
        {
            public function dispatch($message, array $properties = []): void
            {
                throw new \BadMethodCallException("I shall not be called.");
            }

            public function process($message, array $properties = []): void
            {
                throw new \DomainException("I am the expected error.");
            }
        };

        $messageBroker = new class implements MessageBroker
        {
            public function get(): ?MessageEnvelope
            {
                return null;
            }

            public function dispatch(MessageEnvelope $envelope): void
            {
                throw new \BadMethodCallException("I shall not be called.");
            }

            public function ack(MessageEnvelope $envelope): void
            {
                throw new \BadMethodCallException("I shall not be called.");
            }

            public function reject(MessageEnvelope $envelope, ?\Throwable $exception = null): void
            {
                throw new \BadMethodCallException("I shall not be called.");
            }
        };

        $worker = new Worker($dispatcher, $messageBroker);

        $hasIdled = false;
        $eventDispatcher = $worker->getEventDispatcher();

        $eventDispatcher
            ->addListener(
                WorkerEvent::IDLE,
                function () use ($worker, &$hasIdled) {
                    $worker->stop();
                    $hasIdled = true;
                }
            )
        ;

        self::assertFalse($hasIdled);

        $worker->run();

        self::assertTrue($hasIdled);
    }

    public function testIsResilientToError(): void
    {
        $dispatcher = new class implements Dispatcher
        {
            public function dispatch($message, array $properties = []): void
            {
                throw new \BadMethodCallException("I shall not be called.");
            }

            public function process($message, array $properties = []): void
            {
                throw new \DomainException("I am the expected error.");
            }
        };

        $messageBroker = new class implements MessageBroker
        {
            public function get(): ?MessageEnvelope
            {
                return MessageEnvelope::wrap(new \DateTime());
            }

            public function dispatch(MessageEnvelope $envelope): void
            {
                throw new \BadMethodCallException("I shall not be called.");
            }

            public function ack(MessageEnvelope $envelope): void
            {
                throw new \BadMethodCallException("I shall not be called.");
            }

            public function reject(MessageEnvelope $envelope, ?\Throwable $exception = null): void
            {
                throw new \BadMethodCallException("I shall not be called.");
            }
        };

        $worker = new Worker($dispatcher, $messageBroker);

        $caught = false;
        $eventDispatcher = $worker->getEventDispatcher();

        $eventDispatcher
            ->addListener(
                WorkerEvent::NEXT,
                function () use ($worker) {
                    $worker->stop();
                }
            )
        ;

        $eventDispatcher
            ->addListener(
                WorkerEvent::ERROR,
                function () use (&$caught, $worker) {
                    $caught = true;
                    $worker->stop();
                }
            )
        ;

        self::assertFalse($caught);

        $worker->run();

        self::assertTrue($caught);
    }
}
