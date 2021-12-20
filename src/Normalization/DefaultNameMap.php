<?php

declare(strict_types=1);

namespace Goat\Normalization;

/**
 * Default name mapper: use a strategy per context.
 *
 * Brings aliasing as well.
 */
class DefaultNameMap implements NameMap
{
    private NameMappingStrategy $defaultStrategy;
    /** @var array<string,NameMappingStrategy> */
    private array $strategies = [];

    /** @var array<string,array<string,string>> */
    private array $map = [];
    /** @var array<string,array<string,string>> */
    private array $aliases = [];

    public function __construct(?NameMappingStrategy $defaultStrategy = null)
    {
        $this->defaultStrategy = $defaultStrategy ?? new PassthroughNameMappingStrategy();
    }

    /**
     * {@inheritdoc}
     */
    public function logicalNameToPhpType(string $context, string $logicalName): string
    {
        if (isset($this->map[$context][$logicalName])) {
            return $logicalName;
        }

        return $this->aliases[$context][$logicalName] ?? $this->getNameMappingStrategyFor($context)->logicalNameToPhpType($logicalName);
    }

    /**
     * {@inheritdoc}
     */
    public function phpTypeToLogicalName(string $context, string $phpType): string
    {
        if (isset($this->aliases[$context][$phpType])) {
            return $phpType;
        }

        return $this->map[$context][$phpType] ?? $this->getNameMappingStrategyFor($context)->phpTypeToLogicalName($phpType);
    }

    /**
     * @param array<string,string> $map
     *   Keys are PHP type names, values are aliases. Converts PHP type names
     *   to their actual names.
     * @param array<string,string> $aliases
     *   Keys are aliases, values are PHP type names. Converts possibly obsolete
     *   aliases to the real PHP type name. 
     */
    public function setStaticNameMapFor(string $context, array $map, array $aliases = []): void
    {
        $this->map[$context] = $map;
        $this->aliases[$context] = \array_flip($map) + $aliases;
    }

    public function setNameMappingStrategryFor(string $context, NameMappingStrategy $strategy): void
    {
        $this->strategies[$context] = $strategy;
    }

    private function getNameMappingStrategyFor(string $context): NameMappingStrategy
    {
        return $this->strategies[$context] ?? $this->defaultStrategy;
    }
}
