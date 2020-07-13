<?php

declare(strict_types=1);

namespace Goat\Normalization\Tests;

use Goat\EventStore\Tests\MockMessage1;
use Goat\EventStore\Tests\MockMessage2;
use Goat\EventStore\Tests\MockMessage3;
use Goat\Normalization\DefaultNameMap;
use Goat\Normalization\NameMap;
use PHPUnit\Framework\TestCase;

/**
 * Ces tests servent surtout à avoir du coverage
 */
final class DefaultNameMapTest extends TestCase
{
    private $map;

    protected function setUp()
    {
        $this->map = new DefaultNameMap(
            [
                'mock_message_1' => MockMessage1::class,
                'mock_message_3' => MockMessage3::class,
                'varchar' => 'string',
                'dict' => 'array',
            ],
            [
                'mock_2' => MockMessage2::class,
                'mock_3' => 'mock_message_3',
            ]
        );
    }

    public function testMessageNameWithNull()
    {
        $this->assertSame(NameMap::TYPE_NULL, $this->map->getTypeOf(null));
        $this->assertSame(NameMap::TYPE_NULL, $this->map->getAliasOf(null));
    }

    public function testMessageNameWithString()
    {
        $this->assertSame(NameMap::TYPE_STRING, $this->map->getTypeOf("foo"));
        $this->assertSame('varchar', $this->map->getAliasOf("foo"));
    }

    public function testMessageNameWithArray()
    {
        $this->assertSame(NameMap::TYPE_ARRAY, $this->map->getTypeOf(["bar", "baz"]));
        $this->assertSame('dict', $this->map->getAliasOf(["bar", "baz"]));
    }

    public function testMessageNameMapExisting()
    {
        $this->assertSame(MockMessage1::class, $this->map->getTypeOf(new MockMessage1(1, 2, 3)));
        $this->assertSame('mock_message_1', $this->map->getAliasOf(new MockMessage1(1, 2, 3)));
    }

    public function testMessageNameMapNonExisting()
    {
        $this->assertSame(MockMessage2::class, $this->map->getTypeOf(new MockMessage2(1, 2)));
        $this->assertSame(MockMessage2::class, $this->map->getAliasOf(new MockMessage2(1, 2)));
    }

    public function testMessageNameMapAliasedExisting()
    {
        $this->assertSame(MockMessage3::class, $this->map->getTypeOf(new MockMessage3()));
        $this->assertSame('mock_message_3', $this->map->getAliasOf(new MockMessage3()));
    }

    public function testNameTypeWithExisting()    
    {
        $this->assertSame('mock_message_3', $this->map->getAlias(MockMessage3::class));
    }

    public function testGetNameWithNonExisting()
    {
        $this->assertSame(MockMessage2::class, $this->map->getAlias(MockMessage2::class));
    }

    public function testGetTypeWithExisting()
    {
        $this->assertSame(MockMessage1::class, $this->map->getType('mock_message_1'));
    }

    public function testGetTypeWithNonExisting()
    {
        $this->assertSame(MockMessage2::class, $this->map->getType(MockMessage2::class));
    }

    public function testGetTypeWithAliasedExisting()
    {
        $this->assertSame(MockMessage3::class, $this->map->getType('mock_3'));
    }

    public function testGetTypeWithAliasedNonExisting()
    {
        $this->assertSame(MockMessage2::class, $this->map->getType('mock_2'));
    }
}
