<?php

declare(strict_types=1);

namespace Goat\Domain\EventStore;

/**
 * Property names are AMQP compatible, except for 'type', and 'X-*' that should
 * be message properties by the AMQP spec.
 *
 * @codeCoverageIgnore
 */
trait WithPropertiesTrait
{
    private array $properties = [];

    /**
     * Get message identifier property
     */
    public function getMessageId(): ?string
    {
        return $this->getProperty(Property::MESSAGE_ID);
    }

    /**
     * Get application identifier property
     */
    public function getMessageAppId(): ?string
    {
        return $this->getProperty(Property::APP_ID);
    }

    /**
     * Get the content encoding property
     */
    public function getMessageContentEncoding(): ?string
    {
        return $this->getProperty(Property::CONTENT_ENCODING) ?? Property::DEFAULT_CONTENT_ENCODING;
    }

    /**
     * Get the content type property
     */
    public function getMessageContentType(): ?string
    {
        return $this->getProperty(Property::CONTENT_TYPE) ?? Property::DEFAULT_CONTENT_TYPE;
    }

    /**
     * Get the subject property
     */
    public function getMessageSubject(): ?string
    {
        return $this->getProperty(Property::SUBJECT);
    }

    /**
     * Get the user identifier property
     */
    public function getMessageUserId(): ?string
    {
        return $this->getProperty(Property::USER_ID);
    }

    /**
     * Get property value.
     */
    public function getProperty(string $name, ?string $default = null): ?string
    {
        return $this->properties[$name] ?? $default;
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
}
