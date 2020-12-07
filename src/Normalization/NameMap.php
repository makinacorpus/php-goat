<?php

declare(strict_types=1);

namespace Goat\Normalization;

interface NameMap
{
    const CONTEXT_COMMAND = 'command';
    const CONTEXT_EVENT = 'event';
    const CONTEXT_MODEL = 'model';

    /**
     * From logical business name, return PHP type.
     *
     * @param string $context
     *   Arbitrary string which tells in which context we are forging the name.
     */
    public function logicalNameToPhpType(string $context, string $logicalName): string;

    /**
     * From PHP type name, return logical business name.
     *
     * @param string $context
     *   Arbitrary string which tells in which context we are forging the name.
     */
    public function phpTypeToLogicalName(string $context, string $phpType): string;
}
