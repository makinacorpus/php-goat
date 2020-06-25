<?php

declare(strict_types=1);

namespace Goat\Domain\Projector\State;

use Goat\Domain\EventStore\Event;

interface StateStore
{
    /**
     * Lock single projector (which mean it cannot be concurently processed).
     *
     * Raise an exception if projector was already locked.
     *
     * @throws ProjectorLockedError
     */
    public function lock(string $id): State;

    /**
     * Unlock single projector.
     */
    public function unlock(string $id): State;

    /**
     * Update projector state, set its last dispatched event.
     *
     * @throws ProjectorLockedError
     */
    public function update(string $id, Event $event, bool $unlock = true): State;

    /**
     * Update projector state, set error.
     *
     * @throws ProjectorLockedError
     */
    public function error(string $id, Event $event, string $message, int $errorCode = 0, bool $unlock = true): State;

    /**
     * Update projector state, set error using PHP exception.
     *
     * @throws ProjectorLockedError
     */
    public function exception(string $id, Event $event, \Throwable $exception, bool $unlock = true): State;

    /**
     * Get latest event for projector.
     */
    public function latest(string $id): ?State;
}
