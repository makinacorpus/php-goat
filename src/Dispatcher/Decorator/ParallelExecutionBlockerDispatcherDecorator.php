<?php

declare(strict_types=1);

namespace Goat\Dispatcher\Decorator;

use Goat\Dispatcher\Dispatcher;
use Goat\Lock\LockManager;
use MakinaCorpus\Message\Envelope;

final class ParallelExecutionBlockerDispatcherDecorator implements Dispatcher
{
    private Dispatcher $decorated;
    private LockManager $lockService;

    public function __construct(Dispatcher $decorated, LockManager $lockService)
    {
        $this->decorated = $decorated;
        $this->lockService = $lockService;
    }

    /**
     * Synchronous process means we are doing the business transaction.
     *
     * {@inheritdoc}
     */
    public function process($message, array $properties = []): void
    {
        $envelope = Envelope::wrap($message, $properties);
        $message = $envelope->getMessage();

        /*
         * @todo
         *   Restore this using attributes
         *
        if ($message instanceof UnparallelizableMessage) {
            $acquired = false;
            $lockId = $message->getUniqueIntIdentifier();
            try {
                $this->lockService->getLockOrDie($lockId, \get_class($message));
                $acquired = true;
                $this->decorated->process($envelope);
            } finally {
                if ($acquired) {
                    $this->lockService->release($lockId);
                }
            }
        } else {
            $this->decorated->process($envelope);
        }
         */
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
