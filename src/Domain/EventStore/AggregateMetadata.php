<?php

declare(strict_types=1);

namespace Goat\Domain\EventStore;

use Ramsey\Uuid\UuidInterface;

final class AggregateMetadata
{
    private UuidInterface $aggregateId;
    private ?UuidInterface $aggregateRoot = null;
    private string $aggregateType;
    private ?string $aggregateRootType = null;
    private \DateTimeInterface $createdAt;
    private int $currentRevision = 0;
    private string $namespace;

    public function __construct(
        UuidInterface $aggregateId,
        ?UuidInterface $aggregateRoot,
        string $aggregateType,
        ?string $aggregateRootType,
        \DateTimeInterface $createdAt,
        int $currentRevision = 0,
        ?string $namespace = Property::DEFAULT_NAMESPACE
    ) {
        $this->aggregateId = $aggregateId;
        $this->aggregateRoot = $aggregateRoot;
        $this->aggregateType = $aggregateType;
        $this->aggregateRootType = $aggregateRootType;
        $this->createdAt = $createdAt;
        $this->currentRevision = $currentRevision;
        $this->namespace = $namespace;
    }

    public function getAggregateId(): UuidInterface
    {
        return $this->aggregateId;
    }

    public function getAggregateRoot(): ?UuidInterface
    {
        return $this->aggregateRoot;
    }

    public function getCurrentRevision(): int
    {
        return $this->currentRevision;
    }

    public function getAggregateType(): string
    {
        return $this->aggregateType;
    }

    public function getAggregateRootType(): ?string
    {
        return $this->aggregateRootType;
    }

    public function getNamespace(): string
    {
        return $this->namespace;
    }

    public function createdAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }
}
