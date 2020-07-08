<?php

declare(strict_types=1);

namespace Goat\Normalization;

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
