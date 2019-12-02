<?php

declare(strict_types=1);

namespace Goat\Domain\Service;

use Goat\Runner\Runner;
use Goat\Domain\Event\Error\ParallelExecutionError;

/**
 * Domain Service that carries knowledge of how to set and release a lock,
 * a semaphore, something that can ensure your stuff is running in one place
 * only
 */
final class LockService
{
    protected $runner;

    /**
     * Default constructor
     */
    public function __construct(Runner $runner)
    {
        $this->runner = $runner;
    }

    /**
     * Get lock or die
     */
    public function getLockOrDie(int $unique_id, string $name): void
    {
        if (!$this
            ->runner
            ->execute("SELECT pg_try_advisory_xact_lock(?::bigint, 1)", [$unique_id])
            ->fetchField()
        ) {
            throw new ParallelExecutionError(\sprintf('%s event is already running', $name));
        }
    }

    /**
     * Explicit lock release.
     */
    public function release(int $unique_id): void
    {
        // https://vladmihalcea.com/how-do-postgresql-advisory-locks-work/
        //   Ce n'est normalement pas nécessaire, mais je le garde dans un coin.
        // On utilise pg_try_advisory_xact_lock() donc j'ai commenté le unlock,
        // si jamais il venait l'idée d'utiliser de nouveau la variante sans xact
        // il faudrait alors décommenter cette ligne.
        // $this->runner->perform("select pg_advisory_unlock(?::bigint)", [$unique_id]);
    }
}
