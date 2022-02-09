<?php

declare(strict_types=1);

namespace Goat\Dispatcher\Tests\Worker;

use Goat\Dispatcher\Dispatcher;
use Goat\Dispatcher\Worker\Worker;
use Goat\Dispatcher\Worker\WorkerEvent;
use Goat\MessageBroker\MessageBroker;
use MakinaCorpus\Message\Envelope;
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
            public function get(): ?Envelope
            {
                return null;
            }

            public function dispatch(Envelope $envelope): void
            {
                throw new \BadMethodCallException("I shall not be called.");
            }

            public function ack(Envelope $envelope): void
            {
                throw new \BadMethodCallException("I shall not be called.");
            }

            public function reject(Envelope $envelope, ?\Throwable $exception = null): void
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
            public function get(): ?Envelope
            {
                return Envelope::wrap(new \DateTime());
            }

            public function dispatch(Envelope $envelope): void
            {
                throw new \BadMethodCallException("I shall not be called.");
            }

            public function ack(Envelope $envelope): void
            {
                throw new \BadMethodCallException("I shall not be called.");
            }

            public function reject(Envelope $envelope, ?\Throwable $exception = null): void
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
