<?php

declare(strict_types=1);

namespace Goat\Dispatcher\Worker;

use MakinaCorpus\Message\Envelope;

/**
 * Exists for updating UI.
 */
final class WorkerEvent
{
    /** Dispatcher worker starts. */
    const START = 'dispatcher:worker:start';

    /** Dispatcher worker stops. */
    const STOP = 'dispatcher:worker:stop';

    /** Dispatcher worker starts message processing. */
    const NEXT = 'dispatcher:worker:next';

    /** Dispatcher worker starts idle loop. */
    const IDLE = 'dispatcher:worker:idle';

    /** Dispatcher worker message process failed. */
    const ERROR = 'dispatcher:worker:error';

    public static function start(): self
    {
        return new self(self::START);
    }

    public static function stop(): self
    {
        return new self(self::STOP);
    }

    public static function next(Envelope $message): self
    {
        return new self(self::NEXT, $message);
    }

    public static function idle(): self
    {
        return new self(self::IDLE);
    }

    public static function error(): self
    {
        return new self(self::ERROR);
    }

    private string $eventName;
    private ?Envelope $message = null;

    private function __construct(string $eventName, ?Envelope $message = null)
    {
        $this->eventName = $eventName;
        $this->message = $message;
    }

    public function getEventName(): string
    {
        return $this->eventName;
    }

    public function getMessage(): ?Envelope
    {
        return $this->message;
    }
}
