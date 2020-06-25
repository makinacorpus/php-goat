<?php

declare(strict_types=1);

namespace Goat\Domain\Projector\Worker;

use Goat\Domain\Projector\State\State;

/**
 * Exists for updating UI.
 */
final class WorkerEvent
{
    /** Begin play procedure */
    const BEGIN = 'projector:worker:begin';

    /** Move to next event */
    const NEXT = 'projector:worker:next';

    /** A projector raised an error */
    const ERROR = 'projector:worker:error';

    /** End play procedure */
    const END = 'projector:worker:end';

    /** Premature end due to error in all projectors */
    const BROKEN = 'projector:worker:broken';

    private string $eventName;
    private int $streamSize;
    private int $currentPosition = 0;
    private ?State $state = null;

    public static function begin(int $streamSize): self
    {
        return new self(self::BEGIN, $streamSize);
    }

    public static function end(int $streamSize, int $currentPosition = null): self
    {
        return new self(self::END, $streamSize, $currentPosition ?? $streamSize);
    }

    public static function broken(int $streamSize, int $currentPosition = null): self
    {
        return new self(self::BROKEN, $streamSize, $currentPosition ?? $streamSize);
    }

    public static function error(int $streamSize, int $currentPosition, State $state): self
    {
        return new self(self::ERROR, $streamSize, $currentPosition, $state);
    }

    public static function next(int $streamSize, int $currentPosition): self
    {
        return new self(self::NEXT, $streamSize, $currentPosition);
    }

    private function __construct(
        string $eventName,
        int $streamSize,
        int $currentPosition = 0,
        ?State $state = null
    ) {
        $this->eventName = $eventName;
        $this->streamSize = $streamSize;
        $this->currentPosition = $currentPosition;
        $this->state = $state;
    }

    public function getEventName(): string
    {
        return $this->eventName;
    }

    public function getStreamSize(): int
    {
        return $this->streamSize;
    }

    public function getCurrentPosition(): int
    {
        return $this->currentPosition;
    }

    public function getState(): ?State
    {
        return $this->state;
    }
}
