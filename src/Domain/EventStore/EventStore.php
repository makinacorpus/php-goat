<?php

declare(strict_types=1);

namespace Goat\Domain\EventStore;

use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Serializer\SerializerInterface;

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
     *
     * This is the generic implementation of all other event modification
     * methods.
     *
     * An event cannot have its original message modified, nor most of its
     * metadata. Position, revision and creation date as well as the owner
     * aggregate information will NEVER be modified. You cannot mark an event
     * as non existing as well. @todo Implement archiving/hiding events.
     *
     * What you can modify if you need to fix history is:
     *  - revision number, while you cannot specify it, intercaling events
     *    will cause other of the same stream to move as well,
     *  - event properties (or headers) values,
     *  - validity date.
     *
     * In order to re-position an event in history, just change its validity
     * date, and the event query builder will always restitute it in the right
     * order, no matter the event revision.
     *
     * If you need to amend history by adding new events, you can as well use
     * the EventStore::append() method by manually setting the date field, or
     * use the EventStore::insertAfter() method, which will attempt to guess
     * a date that fits between two existing revisions.
     *
     * @see EventStore::moveAfterRevision()
     * @see EventStore::moveAtDate()
     * @see EventStore::failedWith()
     */
    public function update(Event $event): EventBuilder;

    /**
     * Move event from a position after the given revision.
     *
     * If revision is unfound, message will be appended on top of the stream.
     *
     * Validity date will be arbitrarily set somewhere in between the given
     * revision and the next event if any.
     *
     * Warning, if you call the EventBuilder::date() method, behaviour will
     * fallback to EventAmendStore::moveAtDate() method.
     *
     * @param int $afterRevision
     *   Use self::REVISION_TOP to move event at the top of the stream.
     */
    public function moveAfterRevision(Event $event, int $afterRevision): EventBuilder;

    /**
     * Change event validity date to the new one.
     *
     * This an alias of calling EventStore::update()->date($someDate).
     */
    public function moveAtDate(Event $event, \DateTimeInterface $newDate): EventBuilder;

    /**
     * Insert new event after the given revision.
     *
     * If revision is unfound, message will be appended on top of the stream.
     *
     * Validity date will be arbitrarily set somewhere in between the given
     * revision and the next event if any.
     *
     * @param UuidInterface $aggregateId
     *   We need to be able to identify the stream for inserting.
     * @param int $afterRevision
     *   Use self::REVISION_TOP to move event at the top of the stream.
     * @param object $message
     *   Message to store.
     *
     * @return EventBuilder<self>
     *   You need to call execute once done, it will return this session
     *   instance on which you will be able to continue chaining.
     */
    public function insertAfter(UuidInterface $aggregateId, int $afterRevision, object $message, ?string $name = null): EventBuilder;

    /**
     * Mark event as failed and update metadata.
     */
    public function failedWith(Event $event, \Throwable $exception): EventBuilder;

    /**
     * Create event query.
     */
    public function query(): EventQuery;

    /**
     * Find single event by position.
     */
    public function findByPosition(int $position): Event;

    /**
     * Find single event by revision.
     */
    public function findByRevision(UuidInterface $aggregateId, int $revision): Event;

    /**
     * count events hold by this query, ignoring limit.
     *
     * Returns null if the backend does not support count.
     */
    public function count(EventQuery $query): ?int;
}
