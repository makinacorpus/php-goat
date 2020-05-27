<?php

declare(strict_types=1);

namespace Goat\Domain\Event;

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
 * Message can be retried in case of failure.
 *
 * Set this interface on messages that you know for sure failures are due to
 * context, such as transactions or (un)availability of an third party.
 *
 * When then fail, those messages will be re-queued and re-dispatched later
 * no matter which kind of exception they raise.
 */
interface RetryableMessage
{
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
 * Formatted event description.
 */
class EventDescription
{
    /** @var string */
    private $text;

    /** @var mixed[] */
    private $variables = [];

    public function __construct(string $text, array $variables = [])
    {
        $this->text = $text;
        $this->variables = $variables;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function getVariables(): array
    {
        return $this->variables;
    }

    public function format(): string
    {
        return \strtr($this->text, $this->variables);
    }

    /**
     * self::format() alias/
     */
    public function __toString()
    {
        return $this->format();
    }
}

/**
 * Self-decribing event, used for user interface display.
 */
interface WithDescription
{
    /**
     * Describe what happens.
     *
     * Uses an intermediate text representation with EventDescription class
     * which allows displaying code to proceed to variable replacement if
     * necessary. For exemple, it may allow to replace a user identifier
     * with the user full name.
     *
     * How and when replacement will be done is up to each project.
     *
     * For use with Symfony translator component, you should adopt the
     * convention of naming variables using "%" prefix and suffix, for example:
     *
     * @code
     * <?php
     *
     * namespace App\Domain\Event;
     *
     * use Goat\Domain\Event\EventDescription
     * use Goat\Domain\Event\WithDescription
     *
     * final class FooEvent implements WithDescription
     * {
     *     private string $userId;
     *
     *     public static function create(string $userId): self
     *     {
     *         $ret = new self;
     *         $ret->userId = $userId;
     *         return $ret;
     *     }
     *
     *     public function describe(): EventDescription
     *     {
     *         return new EventDescription("%user% said "Foo".", [
     *             "%user%" => $this->userId,
     *         ]);
     *     }
     * }
     * @endcode
     */
    public function describe(): EventDescription;
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
