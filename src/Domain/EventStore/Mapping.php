<?php

declare(strict_types=1);

namespace Goat\Domain\EventStore;

/**
 * Maps PHP native types to normalized names
 */
interface TypeMap
{
    const TYPE_ARRAY = 'array';
    const TYPE_NULL = 'null';
    const TYPE_STRING = 'string';

    /**
     * From message instance guess message PHP native type (denormalized).
     */
    public function getMessageType($message): string;

    /**
     * From PHP native type give the normalized aggregate type.
     */
    public function getType(string $type): string;

    /**
     * Get type map, keys are names, values are types
     */
    public function getTypeMap(): iterable;

    /**
     * Get all aliases, keys are aliases, values are types
     *
     * Type values can be present more than once.
     */
    public function getTypeAliases(): iterable;
}

/**
 * Extends the type map and provide the reverse operation.
 */
interface NameMap extends TypeMap
{
    /**
     * From message instance guess message name (normalized)
     */
    public function getMessageName($message): string;

    /**
     * From message name to PHP native type (denormalization)
     */
    public function getName(string $type): string;
}

/**
 * Default array-based implementation.
 */
final class DefaultNameMap implements NameMap
{
    /** @var string[] */
    private $aliases = [];

    /** @var string[] */
    private $nameToTypeMap = [];

    /** @var string[] */
    private $typeToNameMap = [];

    /**
     * Default constructor
     *
     * @param string $map[]
     *   Keys are message normalize names, values are PHP native types to
     *   convert the messages to, this is a 1:1 map where a PHP message will
     *   be normalize the associated name.
     * @param string[] $aliases
     *   Alias map for incomming normalized message names, this allows you
     *   to map historical names to changed PHP native types, but also map
     *   the same PHP native type over multiple names depending on the
     *   message source.
     *   Keys are legacy or duplicate names, values are either of normalized
     *   name or PHP native types. Althought for maintanability purpose, it's
     *   recommened to always use normalized names instead of PHP types.
     */
    public function __construct(array $map = [], array $aliases = [])
    {
        $this->aliases = $aliases;
        $this->nameToTypeMap = $map;
        $this->typeToNameMap = \array_flip($map);
    }

    /**
     * {@inheritdoc}
     */
    public function getMessageType($message): string
    {
        if (null === $message) {
            return NameMap::TYPE_NULL;
        }
        if (\is_array($message)) {
            return NameMap::TYPE_ARRAY;
        }
        if (\is_string($message) || \is_resource($message)) {
            return NameMap::TYPE_STRING;
        }
        if (\is_object($message)) {
            return \get_class($message);
        }
        // @codeCoverageIgnoreStart
        switch ($type = \gettype($message)) {
            case 'integer':
                return 'int';
            case 'double':
                return 'float';
            default:
                return $type;
        }
        // @codeCoverageIgnoreEnd
    }

    /**
     * {@inheritdoc}
     */
    public function getMessageName($message): string
    {
        $type = $this->getMessageType($message);

        return $this->typeToNameMap[$type] ?? $type;
    }

    /**
     * {@inheritdoc}
     */
    public function getName(string $type): string
    {
        return $this->typeToNameMap[$type] ?? $type;
    }

    /**
     * {@inheritdoc}
     */
    public function getType(string $name): string
    {
        $name = $this->aliases[$name] ?? $name;

        return $this->nameToTypeMap[$name] ?? $name;
    }

    /**
     * {@inheritdoc}
     */
    public function getTypeMap(): iterable
    {
        return $this->nameToTypeMap;
    }

    /**
     * {@inheritdoc}
     */
    public function getTypeAliases(): iterable
    {
        return $this->alias;
    }
}
