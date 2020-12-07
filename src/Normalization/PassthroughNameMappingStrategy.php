<?php

declare(strict_types=1);

namespace Goat\Normalization;

class PassthroughNameMappingStrategy implements NameMappingStrategy
{
    /**
     * {@inheritdoc}
     */
    public function phpTypeToLogicalName(string $phpType): string
    {
        return $phpType;
    }

    /**
     * {@inheritdoc}
     */
    public function logicalNameToPhpType(string $logicalName): string
    {
        return $logicalName;
    }
}

