<?php

declare(strict_types=1);

namespace Goat\Domain\Event;

use Goat\EventStore\Property;
use Goat\EventStore\WithPropertiesTrait;
use Ramsey\Uuid\Uuid;

final class MessageEnvelope
{
    use WithPropertiesTrait;

    private bool $asynchronous = false;
    private object $message;

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
            if (!$message->hasProperty(Property::MESSAGE_ID)) {
                $properties[Property::MESSAGE_ID] = (string)Uuid::uuid4();
            }
            return $message->withProperties($properties);
        }

        if (!isset($properties[Property::MESSAGE_ID])) {
            $properties[Property::MESSAGE_ID] = (string)Uuid::uuid4();
        }

        $ret = new self;
        $ret->message = $message;

        foreach ($properties as $key => $value) {
            if (null === $value || '' === $value) {
                unset($ret->properties[$key]);
            } else {
                $ret->properties[$key] = (string)$value;
            }
        }

        return $ret;
    }

    /**
     * Override properties
     */
    public function withProperties(array $properties): self
    {
        foreach ($properties as $key => $value) {
            if (null === $value || '' === $value) {
                unset($this->properties[$key]);
            } else {
                $this->properties[$key] = (string)$value;
            }
        }

        return $this;
    }

    /**
     * Get internal message.
     */
    public function getMessage(): object
    {
        return $this->message;
    }
}
