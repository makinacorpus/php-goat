<?php

declare(strict_types=1);

namespace Goat\Domain\EventStore;

/**
 * Event query builder
 */
interface EventQuery
{
    /**
     * Set reverse order search (from latest to oldest)
     */
    public function reverse(bool $toggle = false): EventQuery;

    /**
     * Fetch events starting from position
     */
    public function fromPosition(int $position): EventQuery;

    /**
     * Fetch events starting from revision
     */
    public function fromRevision(int $revision): EventQuery;

    /**
     * Fetch events for aggregate
     *
     * @param string|\Ramsey\Uuid\UuidInterface $aggregateId
     */
    public function for($aggregateId, bool $includeRoots = false): EventQuery;

    /**
     * Fetch events that have been failed or not, set null to drop filter
     * default is to always exclude failed events
     */
    public function failed(?bool $toggle = true): EventQuery;

    /**
     * Fetch with aggregate type
     */
    public function withType($typeOrTypes): EventQuery;

    /**
     * Fetch with the given event names
     *
     * @param string|string[] $nameOrNames
     */
    public function withName($nameOrNames): EventQuery;

    /**
     * Fetch the given $name part (insensitive) in the real Name
     */
    public function withSearchName(string $name): EventQuery;

    /**
     * Fetch with the given data in the raw message data
     */
    public function withSearchData($data): EventQuery;

    /**
     * Fetch events starting from date, ignored if date bounds are already set using betweenDate()
     */
    public function fromDate(\DateTimeInterface $from): EventQuery;

    /**
     * Fetch events until date, ignored if date bounds are already set using betweenDate()
     */
    public function toDate(\DateTimeInterface $to): EventQuery;

    /**
     * Fetch event between provided dates, order does not matter, will override fromDate()
     */
    public function betweenDates(\DateTimeInterface $from, \DateTimeInterface $to): EventQuery;

    /**
     * Limit the number of returned rows
     *
     * If given parameter is 0, there is no limit
     */
    public function limit(int $limit): EventQuery;

    /**
     * Execute this query and fetch event stream
     */
    public function execute(): EventStream;
}
