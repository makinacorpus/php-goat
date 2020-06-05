<?php

declare(strict_types=1);

namespace Goat\Domain\EventStore;

use Symfony\Component\Serializer\SerializerInterface;
use Ramsey\Uuid\UuidInterface;

interface EventStore
{
    /**
     * Set namespace map.
     */
    public function setNamespaceMap(NamespaceMap $namespaceMap): void;

    /**
     * Set serializer.
     */
    public function setSerializer(SerializerInterface $serializer, ?string $format = null): void;

    /**
     * Store event.
     *
     * @deprecated
     *   Use self::append() instead.
     */
    public function store(object $message, ?UuidInterface $aggregateId = null, ?string $aggregateType = null, bool $failed = false, array $extra = []): Event;

    /**
     * Append new event.
     *
     * @param object $message
     *   Message to store.
     *
     * @return EventBuilder<Event>
     *   Once executed, event is stored.
     */
    public function append(object $message, ?string $name = null): EventBuilder;

    /**
     * Update event metadata.
     */
    public function update(Event $event, array $properties): Event;

    /**
     * Mark event as failed and update metadata.
     */
    public function failedWith(Event $event, \Throwable $exception, array $properties = []): Event;

    /**
     * Create event query.
     */
    public function query(): EventQuery;

    /**
     * count events hold by this query, ignoring limit.
     *
     * Returns null if the backend does not support count.
     */
    public function count(EventQuery $query): ?int;
}
