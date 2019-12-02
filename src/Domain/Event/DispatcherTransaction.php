<?php

declare(strict_types=1);

namespace Goat\Domain\Event;

/**
 * Reprensents a pending transaction.
 */
final class DispatcherTransaction implements Transaction
{
    /**
     * @var TransactionHandler[]
     */
    private $handlers = [];
    private $running = false;

    /**
     * Default constructor
     */
    public function __construct(iterable $handlers)
    {
        /** @var \Goat\Domain\Event\TransactionHandler $handler */
        foreach ($handlers as $handler) {
            $handler->start();
            $this->handlers[] = $handler;
        }
        $this->running = true;
    }

    /**
     * Is the current transaction still running, i.e. has not been stopped.
     *
     * @internal
     */
    public function isRunning(): bool
    {
        return $this->running;
    }

    /**
     * {@inheritdoc}
     */
    public function commit(): void
    {
        if (!$this->running) {
            throw new \BadMethodCallException("There is no running transaction");
        }

        foreach ($this->handlers as $handler) {
            $handler->commit();
        }

        $this->running = false;
    }

    /**
     * {@inheritdoc}
     */
    public function rollback(?\Throwable $previous = null): void
    {
        if (!$this->running) {
            throw new \BadMethodCallException("There is no running transaction");
        }

        foreach ($this->handlers as $handler) {
            // Let it pass, we must rollback ALL transactions.
            try {
                $handler->rollback($previous);
            } catch (\Throwable $e) {
                $previous = $e;
            }
        }

        $this->running = false;

        // We can't throw more than one exception, we will raise the last one
        // hoping that backends did set the previous exception as being previous
        // for the error
        if ($previous) {
            throw $previous;
        }
    }
}
