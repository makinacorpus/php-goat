<?php

declare(strict_types=1);

namespace Goat\Domain\Tests\EventStore;

use Goat\EventStore\DefaultNameMap;
use Goat\EventStore\NameMap;
use PHPUnit\Framework\TestCase;

/**
 * Ces tests servent surtout à avoir du coverage
 */
final class NameMapTest extends TestCase
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
        $this->assertSame(NameMap::TYPE_NULL, $this->map->getMessageType(null));
        $this->assertSame(NameMap::TYPE_NULL, $this->map->getMessageName(null));
    }

    public function testMessageNameWithString()
    {
        $this->assertSame(NameMap::TYPE_STRING, $this->map->getMessageType("foo"));
        $this->assertSame('varchar', $this->map->getMessageName("foo"));
    }

    public function testMessageNameWithArray()
    {
        $this->assertSame(NameMap::TYPE_ARRAY, $this->map->getMessageType(["bar", "baz"]));
        $this->assertSame('dict', $this->map->getMessageName(["bar", "baz"]));
    }

    public function testMessageNameMapExisting()
    {
        $this->assertSame(MockMessage1::class, $this->map->getMessageType(new MockMessage1(1, 2, 3)));
        $this->assertSame('mock_message_1', $this->map->getMessageName(new MockMessage1(1, 2, 3)));
    }

    public function testMessageNameMapNonExisting()
    {
        $this->assertSame(MockMessage2::class, $this->map->getMessageType(new MockMessage2(1, 2)));
        $this->assertSame(MockMessage2::class, $this->map->getMessageName(new MockMessage2(1, 2)));
    }

    public function testMessageNameMapAliasedExisting()
    {
        $this->assertSame(MockMessage3::class, $this->map->getMessageType(new MockMessage3()));
        $this->assertSame('mock_message_3', $this->map->getMessageName(new MockMessage3()));
    }

    public function testNameTypeWithExisting()    
    {
        $this->assertSame('mock_message_3', $this->map->getName(MockMessage3::class));
    }

    public function testGetNameWithNonExisting()
    {
        $this->assertSame(MockMessage2::class, $this->map->getName(MockMessage2::class));
    }

    public function testGetNameWithAliasedExisting()
    {
        $this->markTestSkipped("I am not sure this will ever be useful");
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
