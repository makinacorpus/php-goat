<?php

declare(strict_types=1);

namespace Goat\Domain\EventStore;

use Ramsey\Uuid\UuidInterface;

/**
 * Event builder.
 *
 * @param EventBuilder<T>
 */
interface EventBuilder
{
    /**
     * Set message, can be a any object.
     */
    public function message(object $message, ?string $type = null): self;

    /**
     * With aggregate information.
     *
     * If no UUID is provided, new one will be generated.
     */
    public function aggregate(string $type, ?UuidInterface $id = null): self;

    /**
     * With given property value.
     *
     * @param ?string $value
     *   If set to null, explicitely remove property.
     */
    public function property(string $name, ?string $value): self;

    /**
     * Execute operation.
     *
     * @return <T>
     *   Where T was defined by the caller.
     *   I WANT GENERICS !
     */
    public function execute();
}
