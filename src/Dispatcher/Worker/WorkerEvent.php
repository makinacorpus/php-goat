<?php

declare(strict_types=1);

namespace Goat\Dispatcher\Worker;

use Goat\Dispatcher\MessageEnvelope;

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

    public static function next(MessageEnvelope $message): self
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
    private ?MessageEnvelope $message = null;

    private function __construct(string $eventName, ?MessageEnvelope $message = null)
    {
        $this->eventName = $eventName;
        $this->message = $message;
    }

    public function getEventName(): string
    {
        return $this->eventName;
    }

    public function getMessage(): ?MessageEnvelope
    {
        return $this->message;
    }
}
