<?php

declare(strict_types=1);

namespace Goat\Domain\EventStore;

use Ramsey\Uuid\UuidInterface;

/**
 * Event builder.
 */
interface EventBuilder
{
    /**
     * Set message name.
     */
    public function name(string $name): self;

    /**
     * Set validity date if not now.
     *
     * Warning: this will not modify the event position, only its date.
     */
    public function date(\DateTimeInterface $date): self;

    /**
     * With aggregate information.
     *
     * If no UUID is provided, new one will be generated.
     */
    public function aggregate(?string $type, ?UuidInterface $id = null): self;

    /**
     * With given property value.
     *
     * @param ?string $value
     *   If set to null, explicitely remove property.
     */
    public function property(string $name, ?string $value): self;

    /**
     * With given mulitple property value.
     *
     * @param array<string,null|string> $properties
     *   If set to null, explicitely remove property.
     */
    public function properties(array $properties): self;

    /**
     * Execute operation.
     */
    public function execute(): Event;
}
