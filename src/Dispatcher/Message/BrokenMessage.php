<?php

declare(strict_types=1);

namespace Goat\Dispatcher\Message;

use Ramsey\Uuid\UuidInterface;

/**
 * When a message cannot be unserialized, you'll get this.
 */
final class BrokenMessage implements Message
{
    use MessageTrait;

    private $data;
    private $dataClass;
    private $aggregateType;

    /**
     * Default constructor
     */
    public function __construct(?string $aggregateType = null, ?UuidInterface $aggregateId = null, $data = null, ?string $dataClass = null)
    {
        $this->aggregateId = $aggregateId;
        $this->aggregateType = $aggregateType;
        $this->data = $data;
        $this->dataClass = $dataClass;
    }

    /**
     * {@inheritdoc}
     */
    public function getAggregateType(): ?string
    {
        return $this->aggregateType;
    }

    /**
     * Get original data
     */
    public function getOriginalData()
    {
        return $this->data;
    }

    /**
     * Get original data class
     */
    public function getOriginalDataClass(): ?string
    {
        return $this->dataClass;
    }
}
