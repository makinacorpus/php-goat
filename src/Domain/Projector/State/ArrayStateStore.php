<?php

declare(strict_types=1);

namespace Goat\Domain\Projector\State;

use Goat\Domain\EventStore\Event;

/**
 * You may decorate this implementation to use another backend such as file
 * storage or such.
 *
 * Otherwise, it's mostly used for tests.
 */
final class ArrayStateStore implements StateStore
{
    /** @var array<string, State> */
    private array $data = [];

    public function __construct(array $data = [])
    {
        foreach ($data as $state) {
            $this->set($state);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function lock(string $id): State
    {
        $existing = $this->latest($id);

        if ($existing) {
            if ($existing->isLocked()) {
                throw new ProjectorLockedError($id);
            }

            return $this->set(
                $existing->clone(
                    null,
                    null,
                    true
                )
            );
        }

        return $this->set(State::empty($id, true));
    }

    /**
     * {@inheritdoc}
     */
    public function unlock(string $id): State
    {
        $existing = $this->latest($id);

        if ($existing) {
            if ($existing->isLocked()) {
                return $this->set(
                    $existing->clone(
                        null,
                        null,
                        false
                    )
                );
            }

            return $existing;
        }

        return $this->set(State::empty($id, false));
    }

    /**
     * {@inheritdoc}
     */
    public function update(string $id, Event $event, bool $unlock = true): State
    {
        $existing = $this->latest($id);

        if ($existing) {
            return $this->set(
                $existing->clone(
                    $event->validAt(),
                    $event->getPosition(),
                    $unlock ? false : $existing->isLocked(),
                    false
                )
            );
        }

        return $this->set(
            $this->create(
                $id,
                $event->validAt(),
                $event->getPosition(),
                false,
                false
            )
        );
    }

    /**
     * {@inheritdoc}
     */
    public function error(string $id, Event $event, string $message, int $errorCode = 0, bool $unlock = true): State
    {
        $existing = $this->latest($id);

        if ($existing) {
            return $this->set(
                $existing->clone(
                    $event->validAt(),
                    $event->getPosition(),
                    false,
                    true,
                    $message,
                    $errorCode
                )
            );
        }

        return $this->set(
            $this->create(
                $id,
                $event->validAt(),
                $event->getPosition(),
                false,
                true,
                $errorCode,
                $message
                // @todo Trace?
            )
        );
    }

    /**
     * {@inheritdoc}
     */
    public function exception(string $id, Event $event, \Throwable $exception, bool $unlock = true): State
    {
        $existing = $this->latest($id);

        if ($existing) {
            return $this->set(
                $existing->clone(
                    $event->validAt(),
                    $event->getPosition(),
                    false,
                    true,
                    $exception->getCode(),
                    $exception->getMessage(),
                    self::normalizeExceptionTrace($exception)
                )
            );
        }

        return $this->set(
            $this->create(
                $id,
                $event->validAt(),
                $event->getPosition(),
                false,
                true,
                $exception->getCode(),
                $exception->getMessage(),
                self::normalizeExceptionTrace($exception)
            )
        );
    }

    /**
     * {@inheritdoc}
     */
    public function latest(string $id): ?State
    {
        return $this->data[$id] ?? null;
    }

    /**
     * Normalize exception trace.
     */
    public static function normalizeExceptionTrace(\Throwable $exception): string
    {
        $output = '';
        do {
            if ($output) {
                $output .= "\n";
            }
            $output .= \sprintf("%s: %s\n", \get_class($exception), $exception->getMessage());
            $output .= $exception->getTraceAsString();
        } while ($exception = $exception->getPrevious());

        return $output;
    }

    /**
     * Set and return item.
     */
    private function set(State $state): State
    {
        return $this->data[$state->getProjectorId()] = $state;
    }

    /**
     * Create new item with data.
     */
    private function create(
        string $id,
        \DateTimeInterface $date,
        int $position = 0,
        bool $isLocked = false,
        bool $isError = false,
        int $errorCode = 0,
        ?string $errorMessage = null,
        ?string $errorTrace = null
    ): State {
        return new State(
            $id,
            new \DateTimeImmutable(),
            new \DateTimeImmutable(),
            $date,
            $position,
            $isLocked,
            $isError,
            $errorCode,
            $errorMessage,
            $errorTrace
        );
    }
}
