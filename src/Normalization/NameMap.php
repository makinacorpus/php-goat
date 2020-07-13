<?php

declare(strict_types=1);

namespace Goat\Normalization;

/**
 * Maps PHP native types to normalized names
 */
interface NameMap
{
    const TYPE_ARRAY = 'array';
    const TYPE_NULL = 'null';
    const TYPE_STRING = 'string';

    /**
     * From message instance, guess and return alias.
     */
    public function getAliasOf($message): string;

    /**
     * From message instance, guess and return type name.
     */
    public function getTypeOf($message): string;

    /**
     * From PHP native type name, return alias.
     */
    public function getAlias(string $type): string;

    /**
     * From alias, return PHP native type name.
     */
    public function getType(string $alias): string;

    /**
     * Get type map, keys are names, values are types
     */
    public function getTypeMap(): iterable;

    /**
     * Get all aliases, keys are aliases, values are types
     *
     * Type values can be present more than once.
     */
    public function getAliasesMap(): iterable;
}
