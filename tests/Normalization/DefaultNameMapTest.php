<?php

declare(strict_types=1);

namespace Goat\Normalization\Tests;

use Goat\EventStore\Tests\MockMessage1;
use Goat\EventStore\Tests\MockMessage2;
use Goat\EventStore\Tests\MockMessage3;
use Goat\Normalization\DefaultNameMap;
use Goat\Normalization\NameMap;
use Goat\Normalization\NameMappingStrategy;
use PHPUnit\Framework\TestCase;

final class DefaultNameMapTest extends TestCase
{
    private $map;

    protected function setUp(): void
    {
        $this->map = new DefaultNameMap();
        $this->map->setStaticNameMapFor(
            NameMap::CONTEXT_COMMAND,
            [
                MockMessage2::class => 'mock_message_2',
                MockMessage3::class => 'mock_message_3',
                'NonExistingClass' => 'non_existing_class',
            ],
            [
                'mock_2' => MockMessage2::class,
                'mock_2_2' => MockMessage2::class,
                'mock_3' => 'mock_message_3',
            ]
        );
        $this->map->setNameMappingStrategryFor(
            NameMap::CONTEXT_COMMAND,
            new class () implements NameMappingStrategy
            {
                public function logicalNameToPhpType(string $logicalName): string
                {
                    if ('%%' !== \substr($logicalName, 0, 2)) {
                        return $logicalName;
                    }
                    return \substr($logicalName, 2);
                }

                public function phpTypeToLogicalName(string $phpType): string
                {
                    if ('%%' === \substr($phpType, 0, 2)) {
                        return $phpType;
                    }
                    return '%%' . $phpType;
                }
            }
        );
    }

    public function testTypeToNameReturnAlias(): void
    {
        self::assertSame(
            'mock_message_2',
            $this->map->phpTypeToLogicalName(NameMap::CONTEXT_COMMAND, MockMessage2::class)
        );
    }

    public function testTypeToNameReturnSameValueIfAlreadyAnAlias(): void
    {
        self::assertSame(
            'mock_message_2',
            $this->map->phpTypeToLogicalName(NameMap::CONTEXT_COMMAND, 'mock_message_2')
        );
    }

    public function testTypeToNameReturnStrategyIfNoAlias(): void
    {
        self::assertSame(
            '%%' . MockMessage1::class,
            $this->map->phpTypeToLogicalName(NameMap::CONTEXT_COMMAND, MockMessage1::class)
        );
    }

    public function testTypeToNameFallsBackOnPassthrough(): void
    {
        self::assertSame(
            MockMessage1::class,
            $this->map->phpTypeToLogicalName(NameMap::CONTEXT_EVENT, MockMessage1::class)
        );
    }

    public function testNameToTypeReturnWorksWithAllAliases(): void
    {
        self::assertSame(
            MockMessage2::class,
            $this->map->logicalNameToPhpType(NameMap::CONTEXT_COMMAND, 'mock_message_2')
        );
        self::assertSame(
            MockMessage2::class,
            $this->map->logicalNameToPhpType(NameMap::CONTEXT_COMMAND, 'mock_2')
        );
        self::assertSame(
            MockMessage2::class,
            $this->map->logicalNameToPhpType(NameMap::CONTEXT_COMMAND, 'mock_2_2')
        );
    }

    public function testNameToTypeReturnSameValueIfAlreadyAType(): void
    {
        self::assertSame(
            MockMessage2::class,
            $this->map->logicalNameToPhpType(NameMap::CONTEXT_COMMAND, MockMessage2::class)
        );
    }

    public function testNameToTypeReturnStrategyIfNoAlias(): void
    {
        self::assertSame(
            MockMessage1::class,
            $this->map->logicalNameToPhpType(NameMap::CONTEXT_COMMAND, '%%' . MockMessage1::class)
        );
    }

    public function testNameToTypeFallsBackOnPassthrough(): void
    {
        self::assertSame(
            MockMessage1::class,
            $this->map->logicalNameToPhpType(NameMap::CONTEXT_EVENT, MockMessage1::class)
        );
    }
}
