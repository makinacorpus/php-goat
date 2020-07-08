<?php

declare(strict_types=1);

namespace Goat\Domain\Event\Decorator;

use Goat\Domain\Event\Dispatcher;
use Goat\Domain\Event\MessageEnvelope;
use Goat\Domain\Event\UnparallelizableMessage;
use Goat\Domain\Service\LockService;

final class ParallelExecutionBlockerDispatcherDecorator implements Dispatcher
{
    private Dispatcher $decorated;
    private LockService $lockService;

    public function __construct(Dispatcher $decorated, LockService $lockService)
    {
        $this->decorated = $decorated;
        $this->lockService = $lockService;
    }

    /**
     * Synchronous process means we are doing the business transaction.
     *
     * {@inheritdoc}
     */
    public function process($message, array $properties = [], bool $withTransaction = true): void
    {
        $envelope = MessageEnvelope::wrap($message, $properties);
        $message = $envelope->getMessage();

        if ($message instanceof UnparallelizableMessage) {
            $acquired = false;
            $lockId = $message->getUniqueIntIdentifier();
            try {
                $this->lockService->getLockOrDie($lockId, \get_class($message));
                $acquired = true;
                $this->decorated->process($envelope, [], $withTransaction);
            } finally {
                if ($acquired) {
                    $this->lockService->release($lockId);
                }
            }
        } else {
            $this->decorated->process($envelope, [], $withTransaction);
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
        $this->decorated->dispatch($message, $properties);
    }
}
