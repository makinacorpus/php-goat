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
    use WithPropertiesTrait;

    /**
     * @deprecated
     * @see Property
     */
    const DEFAULT_CONTENT_ENCODING = 'UTF-8';

    /**
     * @deprecated
     * @see Property
     */
    const DEFAULT_CONTENT_TYPE = 'application/json';

    /**
     * @deprecated
     * @see Property
     */
    const TYPE_DEFAULT = 'none';

    /**
     * @deprecated
     * @see Property
     */
    const NAMESPACE_DEFAULT = 'default';

    /**
     * @deprecated
     * @see Property
     */
    const PROP_APP_ID = 'app-id';

    /**
     * @deprecated
     * @see Property
     */
    const PROP_CONTENT_ENCODING = 'content-encoding';

    /**
     * @deprecated
     * @see Property
     */
    const PROP_CONTENT_TYPE = 'content-type';

    /**
     * @deprecated
     * @see Property
     */
    const PROP_MESSAGE_ID = 'message-id';

    /**
     * @deprecated
     * @see Property
     */
    const PROP_MESSAGE_TYPE = 'type';

    /**
     * @deprecated
     * @see Property
     */
    const PROP_REPLY_TO = 'reply-to';

    /**
     * @deprecated
     * @see Property
     */
    const PROP_SUBJECT = 'subject';

    /**
     * @deprecated
     * @see Property
     */
    const PROP_USER_ID = 'user-id';

    /**
     * @deprecated
     * @see Property
     */
    const PROP_RETRY_COUNT = 'x-retry-count';

    /**
     * @deprecated
     * @see Property
     */
    const PROP_RETRY_DELAI = 'x-retry-delai';

    /**
     * @deprecated
     * @see Property
     */
    const PROP_RETRY_MAX = 'x-retry-max';

    private ?UuidInterface $aggregateId = null;
    private ?UuidInterface $aggregateRoot = null;
    private ?string $aggregateType = null;
    private ?\DateTimeInterface $createdAt = null;
    private ?\DateTimeInterface $validAt = null;
    private $data;
    private ?int $errorCode = null;
    private ?string $errorMessage = null;
    private ?string $errorTrace = null;
    private bool $hasFailed = false;
    private string $name;
    private ?string $namespace = null;
    private int $position = 0;
    private int $revision = 0;

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
     * Get creation date.
     *
     * You MUST NOT use this for business purpose, use validity date instead.
     *
     * @see Event::validAt()
     */
    public function createdAt(): \DateTimeInterface
    {
        return $this->createdAt ?? ($this->createdAt = new \DateTimeImmutable());
    }

    /**
     * Get validity date.
     *
     * Validity date is the moment in time the event is considered done. This
     * field exists because events can be amended to fix history in case of bugs
     * were spotted.
     *
     * Creation date MUST NOT be used for business purposes, only validation
     * date can be.
     */
    public function validAt(): \DateTimeInterface
    {
        return $this->validAt ?? $this->createdAt();
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
