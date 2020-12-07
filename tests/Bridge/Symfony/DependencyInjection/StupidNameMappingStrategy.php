<?php

declare(strict_types=1);

namespace Goat\Bridge\Symfony\Tests\DependencyInjection;

use Goat\Normalization\NameMappingStrategy;

class StupidNameMappingStrategy implements NameMappingStrategy
{
    /**
     * {@inheritdoc}
     */
    public function logicalNameToPhpType(string $logicalName): string
    {
        if ('!' === $logicalName[0]) {
            return \substr($logicalName, 1);
        }
        return $logicalName;
    }

    /**
     * {@inheritdoc}
     */
    public function phpTypeToLogicalName(string $phpType): string
    {
        if ('!' === $phpType[0]) {
            return $phpType;
        }
        return '!' . $phpType;
    }
}
