<?php

declare(strict_types=1);

namespace Goat\Domain\EventStore;

use Goat\Domain\Event\Message;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * Property names are AMQP compatible, except for 'type', and 'X-*' that should
 * be message properties by the AMQP spec.
 */
final class Event
{
    const DEFAULT_CONTENT_ENCODING = 'UTF-8';
    const DEFAULT_CONTENT_TYPE = 'application/json';
    const NAMESPACE_DEFAULT = 'default';
    const PROP_APP_ID = 'app-id';
    const PROP_CONTENT_ENCODING = 'content-encoding';
    const PROP_CONTENT_TYPE = 'content-type';
    const PROP_MESSAGE_ID = 'message-id';
    const PROP_MESSAGE_TYPE = 'type';
    const PROP_REPLY_TO = 'reply-to';
    const PROP_SUBJECT = 'subject';
    const PROP_USER_ID = 'user-id';
    const TYPE_DEFAULT = 'none';

    private $aggregateId;
    private $aggregateRoot;
    private $aggregateType;
    private $createdAt;
    private $data;
    private $errorCode;
    private $errorMessage;
    private $errorTrace;
    private $hasFailed = false;
    private $name;
    private $namespace;
    private $position = 0;
    private $properties = [];
    private $revision = 0;

    /**
     * Normalize name to a camel cased method name
     */
    private static function normalizeName(string $name): string
    {
        return \implode('', \array_map('ucfirst', \preg_split('/[^a-zA-Z1-9]+/', $name)));
    }

    /**
     * Create an new in-memory event, not persisted yet.
     */
    public static function create(object $message, ?string $name = null): self
    {
        $ret = new self;
        if ($message instanceof Message) {
            if ($id = $message->getAggregateId()) {
                $ret->aggregateId = $id;
            }
            if ($type = $message->getAggregateType()) {
                $ret->aggregateType = $type;
            }
            if ($rootId = $message->getAggregateRoot()) {
                $ret->aggregateRoot = $rootId;
            }
        }
        $ret->data = $message;
        $ret->name = $name ?? \get_class($message);

        return $ret;
    }

    /**
     * Get position in the whole namespace
     */
    public function getPosition(): int
    {
        return $this->position;
    }

    /**
     * Compute UUID from internal data
     */
    private function computeUuid(): UuidInterface
    {
        return Uuid::uuid4();
    }

    /**
     * Get aggregate identifier
     */
    public function getAggregateId(): UuidInterface
    {
        return $this->aggregateId ?? ($this->aggregateId = $this->computeUuid());
    }

    /**
     * Get aggregate root identifier, if any set
     */
    public function getAggregateRoot(): ?UuidInterface
    {
        return $this->aggregateRoot;
    }

    /**
     * Get revision for the aggregate
     */
    public function getRevision(): int
    {
        return $this->revision;
    }

    /**
     * Get aggregate type
     */
    public function getAggregateType(): string
    {
        return $this->aggregateType ?? self::TYPE_DEFAULT;
    }

    /**
     * Get event name (the message class name in most case)
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get creation date
     */
    public function createdAt(): \DateTimeInterface
    {
        return $this->createdAt ?? ($this->createdAt = new \DateTimeImmutable());
    }

    /**
     * Has the transaction or publication failed (if it has failed, transaction is considered as rollbacked)
     */
    public function hasFailed(): bool
    {
        return $this->hasFailed;
    }

    /**
     * Is this event persisted
     */
    public function isStored(): bool
    {
        return $this->revision !== 0;
    }

    /**
     * Get message identifier property
     */
    public function getMessageId(): ?string
    {
        return $this->getProperty(self::PROP_MESSAGE_ID);
    }

    /**
     * Get application identifier property
     */
    public function getMessageAppId(): ?string
    {
        return $this->getProperty(self::PROP_APP_ID);
    }

    /**
     * Get the content encoding property
     */
    public function getMessageContentEncoding(): ?string
    {
        return $this->getProperty(self::PROP_CONTENT_ENCODING) ?? self::DEFAULT_CONTENT_ENCODING;
    }

    /**
     * Get the content type property
     */
    public function getMessageContentType(): ?string
    {
        return $this->getProperty(self::PROP_CONTENT_TYPE) ?? self::DEFAULT_CONTENT_TYPE;
    }

    /**
     * Get the subject property
     */
    public function getMessageSubject(): ?string
    {
        return $this->getProperty(self::PROP_SUBJECT);
    }

    /**
     * Get the user identifier property
     */
    public function getMessageUserId(): ?string
    {
        return $this->getProperty(self::PROP_USER_ID);
    }

    /**
     * In case of failure, get error code
     */
    public function getErrorCode(): ?int
    {
        return $this->errorCode;
    }

    /**
     * In case of failure, get error message
     */
    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    /**
     * In case of failure, get error trace
     */
    public function getErrorTrace(): ?string
    {
        return $this->errorTrace;
    }

    /**
     * Get single property value if exists
     */
    public function getProperty(string $name): ?string
    {
        return $this->properties[$name] ?? null;
    }

    /**
     * Get properties
     */
    public function getProperties(): array
    {
        return $this->properties;
    }

    /**
     * Get real message that was stored along
     */
    public function getMessage(): ?object
    {
        if (\is_callable($this->data)) {
            $this->data = \call_user_func($this->data);
        }

        return $this->data;
    }
}
