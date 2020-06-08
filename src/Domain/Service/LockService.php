<?php

declare(strict_types=1);

namespace Goat\Domain\Service;

use Goat\Domain\Event\Error\ParallelExecutionError;
use Goat\Runner\Runner;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;

/**
 * Domain Service that carries knowledge of how to set and release a lock,
 * a semaphore, something that can ensure your stuff is running in one place
 * only.
 */
final class LockService implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    private Runner $runner;

    public function __construct(Runner $runner)
    {
        $this->runner = $runner;
        $this->logger = new NullLogger();
    }

    /**
     * Get lock or die
     */
    public function getLockOrDie(int $uniqueId, string $name): void
    {
        if (!$this
            ->runner
            ->execute("SELECT pg_try_advisory_xact_lock(?::bigint, 1)", [$uniqueId])
            ->fetchField()
        ) {
            $this->logger->warning("Lock {id} FAILED log for {name}", ['id' => $uniqueId, 'name' => $name]);

            throw new ParallelExecutionError(\sprintf('%s event is already running', $name));
        }
        $this->logger->debug("Lock {id} ACQUIRED log for {name}", ['id' => $uniqueId, 'name' => $name]);
    }

    /**
     * Explicit lock release.
     */
    public function release(int $uniqueId): void
    {
        // https://vladmihalcea.com/how-do-postgresql-advisory-locks-work/
        //   Ce n'est normalement pas nécessaire, mais je le garde dans un coin.
        // On utilise pg_try_advisory_xact_lock() donc j'ai commenté le unlock,
        // si jamais il venait l'idée d'utiliser de nouveau la variante sans xact
        // il faudrait alors décommenter cette ligne.
        // $this->runner->perform("select pg_advisory_unlock(?::bigint)", [$unique_id]);
        $this->logger->debug("Lock {id} RELEASED", ['id' => $uniqueId]);
    }
}
