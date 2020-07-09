<?php

declare(strict_types=1);

namespace Goat\Preferences\Domain\Model;

/**
 * More descriptive information about a value.
 */
interface ValueSchema extends ValueType
{
    /**
     * Value name.
     */
    public function getName(): string;

    /**
     * Get value short description.
     */
    public function getLabel(): ?string;

    /**
     * Get complete value description.
     */
    public function getDescription(): ?string;

    /**
     * Get default value if any.
     */
    public function getDefault();

    /**
     * Does it has a default value (null is not a value).
     */
    public function hasDefault(): bool;
}
