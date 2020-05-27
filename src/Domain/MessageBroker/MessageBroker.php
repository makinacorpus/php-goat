<?php

declare(strict_types=1);

namespace Goat\Domain\MessageBroker;

use Goat\Domain\Event\MessageEnvelope;

/**
 * We have our own message broker interface, instead of using symfony/messenger
 * one, it allows us much more flexibility, and expose and first-class citizens
 * message properties without using dynamic ugly stamps, such as:
 *
 *   - message identifier,
 *   - message name,
 *   - content type,
 *   - retry flow control properties,
 *   - and a few more...
 *
 * Most but not all of those are inherited from AMQP and various other message
 * bus implementations or specifications, and provide a much more comfortable
 * basis for working with.
 *
 * For exemple, symfony messenger does not exposes message headers (what here
 * we call properties and headers are merged altogether, differenciation come
 * with the property names) in the message envelope, which means there's much
 * metadata you cannot fetch at the transport level: you cannot therefore
 * propagate this metadata in a structured fashion to your real transport.
 *
 * API is almost identical to symfony/messenger transport one and message bus
 * altogether, with the exception that we enfore MessageEnvelope type on every
 * method, as the common protocol.
 *
 * The idea is that we will implement our own feature-complete broker using
 * this interface, and build a specific dedicated implementation that will
 * transparently proxy symfony/messenger one.
 */
interface MessageBroker
{
    /**
     * Fetch next awaiting message from the queue.
     */
    public function get(): ?MessageEnvelope;

    /**
     * Send message.
     */
    public function dispatch(MessageEnvelope $envelope): void;

    /**
     * Acknowledges a single message.
     */
    public function ack(MessageEnvelope $envelope): void;

    /**
     * Reject or requeue a single message.
     *
     * Re-queing will be decided using envelope properties.
     */
    public function reject(MessageEnvelope $envelope): void;
}
