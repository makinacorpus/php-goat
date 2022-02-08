<?php

declare(strict_types=1);

namespace Goat\Bridge\Symfony\Tests\DependencyInjection;

use MakinaCorpus\Normalization\NameMappingStrategy;

class StupidNameMappingStrategy implements NameMappingStrategy
{
    /**
     * {@inheritdoc}
     */
    public function toPhpType(string $name): string
    {
        if ('!' === $name[0]) {
            return \substr($name, 1);
        }
        return $name;
    }

    /**
     * {@inheritdoc}
     */
    public function fromPhpType(string $phpType): string
    {
        if ('!' === $phpType[0]) {
            return $phpType;
        }
        return '!' . $phpType;
    }
}
