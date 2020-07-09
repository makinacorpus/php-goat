<?php

declare(strict_types=1);

namespace Goat\Preferences\Domain\Model;

/**
 * Necessary information for validating an incomming value.
 */
interface ValueType
{
    /**
     * Get PHP native type for value.
     */
    public function getNativeType(): string;

    /**
     * Is this value an enum.
     */
    public function isEnum(): bool;

    /**
     * Get allowed values, if enum.
     *
     * @return string[]
     */
    public function getAllowedValues(): array;

    /**
     * Is this type a collection.
     */
    public function isCollection(): bool;

    /**
     * Is this collection indexed with strings.
     */
    public function isHashMap(): bool;
}
