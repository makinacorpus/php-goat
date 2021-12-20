<?php

declare(strict_types=1);

namespace Goat\Normalization\Tests;

use Goat\Normalization\PrefixNameMappingStrategy;
use PHPUnit\Framework\TestCase;

class PrefixNameMappingStrategyTest extends TestCase
{
    public function testPrefixMatchFromPhpToLogical(): void
    {
        $strategy = new PrefixNameMappingStrategy('Grumf', '\\Foo\\Bar');

        self::assertSame('Grumf.Fizz.Buzz', $strategy->phpTypeToLogicalName('Foo\\Bar\\Fizz\\Buzz'));
    }

    public function testPrefixMatchFromPhpToLogicalShort(): void
    {
        $strategy = new PrefixNameMappingStrategy('Grumf', '\\Foo\\Bar');

        self::assertSame('Grumf.Fizz', $strategy->phpTypeToLogicalName('Foo\\Bar\\Fizz'));
    }

    public function testPrefixMatchFromPhpToLogicalLong(): void
    {
        $strategy = new PrefixNameMappingStrategy('Grumf', '\\Foo\\Bar');

        self::assertSame('Grumf.Fizz.Buzz.Halt.Catch.Fire', $strategy->phpTypeToLogicalName('Foo\\Bar\\Fizz\\Buzz\\Halt\\Catch\\Fire'));
    }

    public function testPrefixMatchFromPhpToLogicalTooShort(): void
    {
        $strategy = new PrefixNameMappingStrategy('Grumf', '\\Foo\\Bar');

        self::assertSame('Foo\\Bar', $strategy->phpTypeToLogicalName('Foo\\Bar'));
    }

    public function testPrefixMatchFromLogicalToPhp(): void
    {
        $strategy = new PrefixNameMappingStrategy('Grumf', '\\Foo\\Bar');

        self::assertSame('Foo\\Bar\\Fizz\\Buzz', $strategy->logicalNameToPhpType('Grumf.Fizz.Buzz'));
    }

    public function testPrefixMatchFromLogicalToPhpShort(): void
    {
        $strategy = new PrefixNameMappingStrategy('Grumf', '\\Foo\\Bar');

        self::assertSame('Foo\\Bar\\Fizz', $strategy->logicalNameToPhpType('Grumf.Fizz'));
    }

    public function testPrefixMatchFromLogicalToPhpLong(): void
    {
        $strategy = new PrefixNameMappingStrategy('Grumf', '\\Foo\\Bar');

        self::assertSame('Foo\\Bar\\Fizz\\Buzz\\Halt\\Catch\\Fire', $strategy->logicalNameToPhpType('Grumf.Fizz.Buzz.Halt.Catch.Fire'));
    }

    public function testPrefixMatchFromLogicalToPhpTooShort(): void
    {
        $strategy = new PrefixNameMappingStrategy('Grumf', '\\Foo\\Bar');

        self::assertSame('Grumf', $strategy->logicalNameToPhpType('Grumf'));
    }

    public function testPrefixDoesNotMatchFromPhpToLogical(): void
    {
        $strategy = new PrefixNameMappingStrategy('Grumf', '\\Foo\\Bar');

        // No conversion.
        self::assertSame('Bar\\Foo\\Bla', $strategy->phpTypeToLogicalName('Bar\\Foo\\Bla'));
    }

    public function testPrefixDoesNotMatchFromLogicalToPhp(): void
    {
        $strategy = new PrefixNameMappingStrategy('Grumf', '\\Foo\\Bar');

        // No conversion.
        self::assertSame('Bar.Foo.Bla', $strategy->logicalNameToPhpType('Bar.Foo.Bla'));
    }
}
