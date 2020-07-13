<?php

declare(strict_types=1);

namespace Goat\Dispatcher\Worker;

interface WorkerRemoteSignalReader
{
    /**
     * Should the current worker restart?
     */
    public function shouldRestart(\DateTimeInterface $startedAt): bool;

    /**
     * Should the current worker stop?
     */
    public function shouldStop(\DateTimeInterface $startedAt): bool;
}
