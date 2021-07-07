<?php

declare(strict_types=1);

namespace Goat\Preferences\Domain\Repository;

use Goat\Preferences\Domain\Model\ValueType;

/**
 * Preference access interface.
 *
 * NONE OF THIS OBJECTS METHODS CAN BE CALLED AT RUNTIME EXCEPT get().
 *
 * You've been warn, drivers are allowed to be extremely slow, except
 * when the get() method is being called.
 */
interface PreferencesRepository extends Preferences
{
    /**
     * Does it has a value for
     */
    public function has(string $name): bool;

    /**
     * Fetch multiple values at once
     *
     * @return mixed[]
     *   Keys are names, values are values, missing values not in there
     */
    public function getMultiple(array $names): array;

    /**
     * Get value type if the value exists, otherwise "string".
     */
    public function getType(string $name): ValueType;

    /**
     * Store a value
     */
    public function set(string $name, $value, ?ValueType $type = null): void;

    /**
     * Delete value
     */
    public function delete(string $name): void;
}
