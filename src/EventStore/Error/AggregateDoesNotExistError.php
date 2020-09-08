<?php

declare(strict_types=1);

namespace Goat\EventStore\Error;

use Ramsey\Uuid\UuidInterface;

class AggregateDoesNotExistError extends \InvalidArgumentException
{
    private ?UuidInterface $aggregateId = null;

    public static function fromAggregateId(UuidInterface $aggregateId, ?\Throwable $previous = null): self
    {
        if ($previous) {
            $error = new self(\sprintf("Aggregate '%s' does not exist", $aggregateId->toString()), 0, $previous);
        } else {
            $error = new self(\sprintf("Aggregate '%s' does not exist", $aggregateId->toString()));
        }

        $error->aggregateId = $aggregateId;

        return $error;
    }

    public function getAggregateId(): ?UuidInterface
    {
        return $this->aggregateId;
    }
}
