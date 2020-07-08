<?php

declare(strict_types=1);

namespace Goat\Dispatcher\Message;

use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * Base implementation that just stores data into a payload array.
 */
trait MessageTrait /* implements Message */
{
    private $aggregateId;
    private $aggregateRoot;

    /**
     * Default constructor
     */
    public function __construct(?UuidInterface $aggregateId = null, ?UuidInterface $aggregateRoot = null)
    {
        $this->aggregateId = $aggregateId;
        $this->aggregateRoot = $aggregateRoot;
    }

    /**
     * {@inheritdoc}
     */
    public function getAggregateId(): UuidInterface
    {
        return $this->aggregateId ?? ($this->aggregateId = Uuid::uuid4());
    }

    /**
     * {@inheritdoc}
     */
    public function getAggregateRoot(): ?UuidInterface
    {
        return $this->aggregateRoot;
    }

    /**
     * {@inheritdoc}
     */
    public function getAggregateType(): ?string
    {
        return null;
    }
}
