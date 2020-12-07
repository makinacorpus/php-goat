<?php

declare(strict_types=1);

namespace Goat\Preferences\Tests;

use Goat\Preferences\Domain\Handler\PreferencesHandler;
use Goat\Preferences\Domain\Message\PreferenceValueDelete;
use Goat\Preferences\Domain\Message\PreferenceValueSet;
use Goat\Preferences\Domain\Message\PreferenceValueSetMany;
use Goat\Preferences\Domain\Repository\ArrayPreferencesRepository;
use Goat\Preferences\Domain\Repository\ArrayPreferencesSchema;
use Goat\Preferences\Domain\Repository\PreferencesRepository;
use PHPUnit\Framework\TestCase;

/**
 * Tests the message handler.
 */
final class HandlerTest extends TestCase
{
    /**
     * Prepare handler
     */
    private static function prepareHandler()
    {
        $repository = new ArrayPreferencesRepository();
        $schema = new ArrayPreferencesSchema([
            'foo' => [
                'type' => 'int',
            ],
            'bar' => [
                'type' => 'string',
            ],
        ]);

        return [$repository, new PreferencesHandler($repository, $schema)];
    }

    /**
     * Prepare handler without schema
     */
    private static function prepareHandlerWithoutSchema()
    {
        $repository = new ArrayPreferencesRepository();

        return [$repository, new PreferencesHandler($repository)];
    }

    /**
     * Data provider
     */
    public static function dataHandler()
    {
        yield self::prepareHandler();
    }

    /**
     * Data provider
     */
    public static function dataHandlerWithoutSchema()
    {
        yield self::prepareHandlerWithoutSchema();
    }

    /**
     * Data provider
     */
    public static function dataHandlerBoth()
    {
        yield self::prepareHandler();
        yield self::prepareHandlerWithoutSchema();
    }

    /**
     * @dataProvider dataHandlerBoth
     */
    public function testSet(PreferencesRepository $repository, PreferencesHandler $handler)
    {
        self::assertFalse($repository->has('foo'));

        $handler->doSet(new PreferenceValueSet('foo', 36));

        self::assertSame(36, $repository->get('foo'));
    }

    /**
     * @dataProvider dataHandlerBoth
     */
    public function testSetMany(PreferencesRepository $repository, PreferencesHandler $handler)
    {
        self::assertFalse($repository->has('foo'));
        self::assertFalse($repository->has('bar'));

        $handler->doSetMany(new PreferenceValueSetMany(['foo' => 17, 'bar' => 'bar']));

        self::assertSame(17, $repository->get('foo'));
        self::assertSame('bar', $repository->get('bar'));
    }

    /**
     * @dataProvider dataHandlerBoth
     */
    public function testDelete(PreferencesRepository $repository, PreferencesHandler $handler)
    {
        $repository->set('foo', 11);
        self::assertTrue($repository->has('foo'));

        $handler->doDelete(new PreferenceValueDelete('foo'));

        self::assertFalse($repository->has('foo'));
    }

    /**
     * @dataProvider dataHandler
     */
    public function testSetTypeMismatchRaiseError(PreferencesRepository $repository, PreferencesHandler $handler)
    {
        self::expectException(\InvalidArgumentException::class);
        $handler->doSet(new PreferenceValueSet('foo', 'pouet'));
    }

    /**
     * @dataProvider dataHandler
     */
    public function testSetManyTypeMismatchRaiseError(PreferencesRepository $repository, PreferencesHandler $handler)
    {
        self::expectException(\InvalidArgumentException::class);
        $handler->doSetMany(new PreferenceValueSetMany([
            'foo' => 12,
            'bar' => 37, // Mismatch
        ]));
    }

    /**
     * @dataProvider dataHandler
     */
    public function testSetNonExistingRaiseError(PreferencesRepository $repository, PreferencesHandler $handler)
    {
        self::expectException(\InvalidArgumentException::class);
        $handler->doSet(new PreferenceValueSet('boom', 12));
    }

    /**
     * @dataProvider dataHandler
     */
    public function testSetNonExistingMismatchRaiseError(PreferencesRepository $repository, PreferencesHandler $handler)
    {
        self::expectException(\InvalidArgumentException::class);
        $handler->doSetMany(new PreferenceValueSetMany([
            'foo' => 12,
            'pouet' => 37, // Non existing
        ]));
    }

    /**
     * @dataProvider dataHandler
     */
    public function testSetManySingleErrorSavesNothing(PreferencesRepository $repository, PreferencesHandler $handler)
    {
        try {
            $handler->doSetMany(new PreferenceValueSetMany([
                'foo' => 12,
                'pouet' => 37, // Non existing
            ]));
            self::fail();
        } catch (\InvalidArgumentException $e) {
            // OK.
        }

        self::assertFalse($repository->has('foo'));
        self::assertFalse($repository->has('pouet'));
    }
}
