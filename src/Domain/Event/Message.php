<?php

declare(strict_types=1);

namespace Goat\Domain\Event;

use Goat\Domain\EventStore\Event as StoredEvent;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * Base implementation that just stores data into a payload array.
 */
interface Message
{
    /**
     * Get target aggregate identifier
     */
    public function getAggregateId(): UuidInterface;

    /**
     * Get root aggregate identifier
     */
    public function getAggregateRoot(): ?UuidInterface;

    /**
     * Get target aggregate type
     */
    public function getAggregateType(): ?string;
}

/**
 * Event is a message that advertise a state change: it happened.
 * It can be consumed by any number of consummers, it should not
 * trigger system state changes, it may or may be not consummed.
 */
interface Event extends Message
{
}

/**
 * Command is a message that triggers a change: it has not happened yet.
 * It can only be sent a single consumer, the only exception is in case
 * of failure it may be retried by someone else.
 */
interface Command extends Message
{
}

/**
 * Messages of the same type (ie. class name) implementing this interface cannot
 * run in parallele and will be blocked. Any blocked message will fail and will
 * not be retried.
 */
interface UnparallelizableMessage
{
    /**
     * Unique identifier, every cron that have the same identifier
     * will lock each others, be careful when attributing a new
     * identifier within the same application.
     */
    public function getUniqueIntIdentifier(): int;
}

/**
 * Message with arbitrary log message
 */
interface WithLogMessage
{
    /**
     * Get log messages
     */
    public function getLogMessage(): ?string;
}

/**
 * Default implementation for WithLogMessage
 */
trait WithLogMessageTrait /* implements WithLogMessage */
{
    private $logMessage;

    /**
     * {@inheritdoc}
     */
    public function getLogMessage(): ?string
    {
        return $this->logMessage;
    }
}

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
    public function __construct(
        ?string $aggregateType = null, ?UuidInterface $aggregateId = null,
        $data = null, ?string $dataClass = null
    ) {
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

/**
 * Default event implementation, just extend this class.
 */
final class MessageEnvelope
{
    private $asynchronous = false;
    private $message;
    private $properties = [];

    /**
     * Default constructor
     */
    private function __construct()
    {
    }

    /**
     * Create instance from message
     */
    public static function wrap($message, array $properties = [])
    {
        if (!\is_object($message)) {
            throw new \TypeError(sprintf('Invalid argument provided to "%s()": expected object, but got %s.', __METHOD__, \gettype($message)));
        }

        if ($message instanceof self) {
            if (!$message->hasProperty(StoredEvent::PROP_MESSAGE_ID)) {
                $properties[StoredEvent::PROP_MESSAGE_ID] = (string)Uuid::uuid4();
            }
            return $message->withProperties($properties);
        }

        if (!isset($properties[StoredEvent::PROP_MESSAGE_ID])) {
            $properties[StoredEvent::PROP_MESSAGE_ID] = (string)Uuid::uuid4();
        }

        $ret = new self;
        $ret->message = $message;
        $ret->properties = $properties;

        return $ret;
    }

    /**
     * Override properties
     */
    public function withProperties(array $properties): self
    {
        foreach ($properties as $key => $value) {
            $this->properties[$key] = $value;
        }
        return $this;
    }

    /**
     * Does the given property is set
     */
    public function hasProperty(string $name): bool
    {
        return isset($this->properties[$name]);
    }

    /**
     * Get properties
     */
    public function getProperties(): array
    {
        return $this->properties;
    }

    /**
     * Get properties compatible with AMQP
     */
    public function getAmqpProperties(): array
    {
        if ($this->properties) {
            return StoredEvent::toAmqpProperties($this->properties);
        }
        return [];
    }

    /**
     * Get internal message
     *
     * @return object
     */
    public function getMessage()
    {
        return $this->message;
    }
}