<?php

declare(strict_types=1);

namespace Goat\Domain\EventStore;

use Ramsey\Uuid\UuidInterface;

/**
 * Default event builder implementation.
 */
final class DefaultEventBuilder implements EventBuilder
{
    private bool $locked = false;
    private ?object $message = null;
    private ?string $name = null;
    private ?string $aggregateType = null;
    private ?UuidInterface $aggregateId = null;
    private ?\DateTimeInterface $date = null;
    private array $properties = [];
    /** @var callable */
    private $execute;

    /**
     * @param callable $execute
     *   Callable must take exactly one argument, which is this builder
     *   instance, and return an Event instance.
     */
    public function __construct(callable $execute)
    {
        $this->execute = $execute;
    }

    /**
     * Set message.
     */
    public function message(object $message): self
    {
        $this->failIfLocked();

        $this->message = $message;

        return $this;
    }

    /**
     * Set message name.
     */
    public function name(string $name): self
    {
        $this->failIfLocked();

        $this->name = $name;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function date(\DateTimeInterface $date): self
    {
        $this->failIfLocked();

        $this->date = $date;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function aggregate(?string $type, ?UuidInterface $id = null): self
    {
        $this->failIfLocked();

        $this->aggregateType = $type;
        $this->aggregateId = $id;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function property(string $name, ?string $value): self
    {
        $this->failIfLocked();

        $this->properties[$name] = $value;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function properties(array $properties): self
    {
        $this->failIfLocked();

        foreach ($properties as $name => $value) {
            $this->properties[$name] = $value;
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function execute(): Event
    {
        $this->failIfLocked();
        $this->locked = true;

        return \call_user_func($this->execute, $this);
    }

    public function getMessage()
    {
        if (null === $this->message) {
            throw new \BadMethodCallException(\sprintf("%s::message() must be called.", EventBuilder::class));
        }

        return $this->message;
    }

    public function getMessageName(): ?string
    {
        return $this->name;
    }

    public function getAggregateId(): ?UuidInterface
    {
        return $this->aggregateId;
    }

    public function getAggregateType(): ?string
    {
        return $this->aggregateType;
    }

    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }

    public function getProperties(): array
    {
        return $this->properties;
    }

    private function failIfLocked(): void
    {
        if ($this->locked) {
            throw new \BadMethodCallException("Event builder was executed, it cannot be modified anymore.");
        }
    }
}
