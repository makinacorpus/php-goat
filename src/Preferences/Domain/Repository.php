<?php

declare(strict_types=1);

namespace Goat\Preferences\Domain\Repository;

use Goat\Preferences\Domain\Model\ValueSchema;
use Goat\Preferences\Domain\Model\ValueType;

/**
 * Preferences schema repository.
 *
 * If you have none, then repositories will be in YOLO mode.
 */
interface PreferencesSchema
{
    /**
     * Does this value type exists in schema
     */
    public function has(string $name): bool;

    /**
     * Get value type from schema
     *
     * @throws \InvalidArgumentException
     *   If value does not exist and schema was not declared
     */
    public function getType(string $name): ValueSchema;

    /**
     * Fetch default value of a value if possible
     *
     * @return mixed
     *   Return type depends upon schema
     *
     * @throws \InvalidArgumentException
     *   If value does not exist and schema was not declared
     */
    public function getDefault(string $name);

    /**
     * Does it has a default value (null is not a value)
     */
    public function hasDefault(string $name): bool;
}

/**
 * Preference access interface.
 *
 * NONE OF THIS OBJECTS METHODS CAN BE CALLED AT RUNTIME EXCEPT get().
 *
 * You've been warn, drivers are allowed to be extremely slow, except
 * when the get() method is being called.
 */
interface PreferencesRepository
{
    /**
     * Does it has a value for
     */
    public function has(string $name): bool;

    /**
     * Fetch a value
     *
     * @return mixed
     *   Return type depends upon schema
     */
    public function get(string $name);

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
    public function delete(string $name);
}

/**
 * Preferences reader.
 */
interface Preferences
{
    /**
     * Fetch current value for preference
     *
     * If variable name does not exists, it will not fail and just return null.
     */
    public function get(string $name);
}

/**
 * Preference reader implementation.
 *
 * Caching will be implemented around this class.
 */
final class DefaultPreferences implements Preferences
{
    /** @var PreferencesRepository */
    private $repository;

    /** @var null|PreferencesSchema */
    private $schema;

    /**
     * Default constructor
     */
    public function __construct(PreferencesRepository $repository, ?PreferencesSchema $schema = null)
    {
        $this->repository = $repository;
        $this->schema = $schema;
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $name)
    {
        $value = $this->repository->get($name);

        if (null === $value && $this->schema) {
            return $this->schema->getDefault($name);
        }

        return $value;
    }
}
