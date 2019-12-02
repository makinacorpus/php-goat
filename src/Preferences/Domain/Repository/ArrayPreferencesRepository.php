<?php

declare(strict_types=1);

namespace Goat\Preferences\Domain\Repository;

use Goat\Preferences\Domain\Model\ValueType;
use Goat\Preferences\Domain\Model\ValueValidator;

/**
 * SQL based implementation
 */
final class ArrayPreferencesRepository implements PreferencesRepository
{
    /** @var mixed[] */
    private $data;

    /**
     * Default constructor
     *
     * @codeCoverageIgnore
     *   Because it is called within a data provider.
     */
    public function __construct(?array $data = null)
    {
        $this->data = $data;
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $name): bool
    {
        return isset($this->data[$name]);
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $name)
    {
        return $this->data[$name] ?? null;
    }

    /**
     * {@inheritdoc}
     */
    public function getMultiple(array $names): array
    {
        return \array_intersect_key($this->data, \array_flip($names));
    }

    /**
     * {@inheritdoc}
     */
    public function getType(string $name): ValueType
    {
        return ValueValidator::getTypeOf($this->get($name));
    }

    /**
     * {@inheritdoc}
     */
    public function set(string $name, $value, ?ValueType $type = null): void
    {
        $this->data[$name] = $value;
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $name)
    {
        unset($this->data[$name]);
    }
}
