<?php

declare(strict_types=1);

namespace Goat\Domain\Projector\State;

final class State
{
    private string $id;
    private \DateTimeInterface $createdAt;
    private \DateTimeInterface $updatedAt;
    private \DateTimeInterface $date;
    private int $position = 0;
    private bool $isLocked = false;
    private bool $isError = false;
    private int $errorCode = 0;
    private ?string $errorMessage = null;
    private ?string $errorTrace = null;

    public function __construct(
        string $id,
        \DateTimeInterface $createdAt,
        \DateTimeInterface $updatedAt,
        \DateTimeInterface $date,
        int $position = 0,
        bool $isLocked = false,
        bool $isError = false,
        int $errorCode = 0,
        ?string $errorMessage = null,
        ?string $errorTrace = null
    ) {
        $this->id = $id;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
        $this->date = $date;
        $this->position = $position;
        $this->isLocked = $isLocked;
        $this->isError = $isError;
        $this->errorCode = $errorCode;
        $this->errorMessage = $errorMessage;
        $this->errorTrace = $errorTrace;
    }

    public static function empty(string $id, bool $isLocked = false): self
    {
        return new self(
            $id,
            $now = new \DateTimeImmutable(),
            $now,
            new \DateTimeImmutable("@0"),
            0,
            $isLocked
        );
    }

    public function getProjectorId(): string
    {
        return $this->id;
    }

    public function getCreationDate(): \DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function getUpdateDate(): \DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function getLatestEventDate(): \DateTimeInterface
    {
        return $this->date;
    }

    public function getLatestEventPosition(): int
    {
        return $this->position;
    }

    public function isLocked(): bool
    {
        return $this->isLocked;
    }

    public function isError(): bool
    {
        return $this->isError;
    }

    public function getErrorCode(): int
    {
        return $this->errorCode;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function getErrorTrace(): ?string
    {
        return $this->errorTrace;
    }

    /**
     * For unit testing.
     *
     * @internal
     * @see ArrayStateStore
     */
    public function clone(
        \DateTimeInterface $date = null,
        ?int $position = null,
        ?bool $isLocked = null,
        ?bool $isError = null,
        ?int $errorCode = null,
        ?string $errorMessage = null,
        ?string $errorTrace = null
    ): self {
        $ret = clone $this;
        $ret->createdAt = clone $ret->createdAt;
        $ret->updatedAt = new \DateTimeImmutable();

        if (null !== $date) {
            $ret->date = $date;
        }
        if (null !== $position) {
            $ret->position = $position;
        }
        if (null !== $isLocked) {
            $ret->isLocked = $isLocked;
        }

        if (null !== $errorCode) {
            $ret->errorCode = $errorCode;
        }
        if (null !== $isError) {
            $ret->isError = $isError;
            if ($isError) {
                $ret->errorMessage = $errorMessage;
                $ret->errorTrace = $errorTrace;
            } else {
                $ret->errorCode = $errorCode ?? 0;
                $ret->errorMessage = null;
                $ret->errorTrace = null;
            }
        }

        return $ret;
    }
}
