<?php

declare(strict_types=1);

namespace Goat\Domain\EventStore;

use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

abstract class AbstractEventQuery implements EventQuery
{
    protected ?UuidInterface $aggregateId = null;
    protected bool $aggregateAsRoot = false;
    protected array $aggregateTypes = [];
    protected ?\DateTimeInterface $dateHigherBound = null;
    protected ?\DateTimeInterface $dateLowerBound = null;
    protected ?bool $failed = false;
    protected int $limit = 0;
    protected array $names = [];
    protected ?string $searchName = null;
    protected $searchData = null;
    protected ?int $position = null;
    protected bool $reverse = false;
    protected ?int $revision = null;

    /**
     * Convert value to UUID, raise exception in case of failure
     */
    private function validateUuid($uuid): UuidInterface
    {
        if (\is_string($uuid)) {
            $uuid = Uuid::fromString($uuid);
        }
        if (!$uuid instanceof UuidInterface) {
            throw new \InvalidArgumentException(\sprintf("Aggregate identifier must be a valid UUID string or instanceof of %s: '%s' given", UuidInterface::class, (string)$uuid));
        }
        return $uuid;
    }

    /**
     * {@inheritdoc}
     */
    public function reverse(bool $toggle = false): EventQuery
    {
        $this->reverse = $toggle;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function fromPosition(int $position): EventQuery
    {
        $this->position = $position;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function fromRevision(int $revision): EventQuery
    {
        $this->revision = $revision;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function for($aggregateId, bool $includeRoots = false): EventQuery
    {
        $this->aggregateId = $this->validateUuid($aggregateId);
        $this->aggregateAsRoot = $includeRoots;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function failed(?bool $toggle = true): EventQuery
    {
        $this->failed = $toggle;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function withType($typeOrTypes): EventQuery
    {
        \assert(\is_array($typeOrTypes) || \is_string($typeOrTypes));

        $this->aggregateTypes = \array_unique($this->aggregateTypes += \array_values((array)$typeOrTypes));

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function withName($nameOrNames): EventQuery
    {
        \assert(\is_array($nameOrNames) || \is_string($nameOrNames));

        $this->names = \array_unique($this->names += \array_values((array)$nameOrNames));

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function withSearchName(string $name): EventQuery
    {
        $this->searchName = $name;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function withSearchData($data): EventQuery
    {
        $this->searchData = $data;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function toDate(\DateTimeInterface $to): EventQuery
    {
        if ($this->dateHigherBound) {
            \trigger_error(\sprintf("Query has already betweenDates() set, toDate() call is ignored"), E_USER_WARNING);
        } else {
            $this->dateHigherBound = $to;
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function fromDate(\DateTimeInterface $from): EventQuery
    {
        if ($this->dateHigherBound) {
            \trigger_error(\sprintf("Query has already betweenDates() set, fromDate() call is ignored"), E_USER_WARNING);
        } else {
            $this->dateLowerBound = $from;
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function betweenDates(\DateTimeInterface $from, \DateTimeInterface $to): EventQuery
    {
        if ($this->dateLowerBound && !$this->dateHigherBound) {
            \trigger_error(\sprintf("Query has already fromDate() set, betweenDates() call overrides it"), E_USER_WARNING);
        }

        if ($from < $to) {
            $this->dateLowerBound = $from;
            $this->dateHigherBound = $to;
        } else {
            $this->dateLowerBound = $to;
            $this->dateHigherBound = $from;
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function limit(int $limit): EventQuery
    {
        if ($limit < 0) {
            throw new \InvalidArgumentException(\sprintf("Limit cannot be less than 0"));
        }

        $this->limit = $limit;

        return $this;
    }
}
