<?php

declare(strict_types=1);

namespace Goat\Domain\EventStore;

/**
 * Namespace map tells us where to store events, if you have more than one
 * table for storing them: each aggregate type can belong a to a specific
 * namespace, which allows to segregate storage depending on it. Per default,
 * all aggregates go to the 'default' namespace and such are stored in the
 * 'event_default' SQL table when using the SQL backend.
 */
final class NamespaceMap
{
    private $map = [];

    /**
     * Default constructor
     */
    public function __construct(array $map = [])
    {
        $this->map = $map;
    }

    /**
     * Fetch namespace for aggregate type
     */
    public function getNamespace(string $aggregateType): string
    {
        return $this->map[$aggregateType] ?? Property::DEFAULT_NAMESPACE;
    }
}
