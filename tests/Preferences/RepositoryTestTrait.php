<?php

declare(strict_types=1);

namespace Goat\Preferences\Tests;

use Goat\Preferences\Domain\Model\ValueValidator;
use Goat\Preferences\Domain\Repository\PreferencesRepository;
use Ramsey\Uuid\Uuid;

trait RepositoryTestTrait
{
    /**
     * Create repository.
     */
    protected abstract function getRepositories(): iterable;

    /**
     * From getRepositories creates a data provider
     */
    public function getRepositoriesDataProvider()
    {
        foreach ($this->getRepositories() as $repository) {
            yield [$repository];
        }
    }

    /**
     * Data provider
     */
    public function dataTestGetHas()
    {
        foreach ($this->getRepositories() as $repository) {
            yield [$repository, 'some_int', 12];
            yield [$repository, 'some_string', "boo"];
            yield [$repository, 'some_date', new \DateTimeImmutable()];
            yield [$repository, 'some_uuid', Uuid::uuid4()];
        }
    }

    /**
     * @dataProvider dataTestGetHas
     */
    public function testGetHas(PreferencesRepository $repository, string $key, $value)
    {
        $name = \uniqid('some.value');
        self::assertFalse($repository->has($name));

        $repository->set($name, $value);
        self::assertTrue($repository->has($name));

        $loaded = $repository->get($name);
        if (\is_object($value)) {
            // We cast, because object references will NOT be the same.
            if (\method_exists($value, '__toString')) {
                self::assertSame((string)$value, (string)$loaded);
            }

            // Ensure a few type information as been correctly stored.
            $valueType = ValueValidator::getTypeOf($value);
            $loadedType = ValueValidator::getTypeOf($loaded);
            self::assertSame($valueType->getNativeType(), $loadedType->getNativeType());
            self::assertSame($valueType->isCollection(), $loadedType->isCollection());
        } else {
            self::assertSame($value, $repository->get($name));
        }
    }

    /**
     * Test a simple getMultiple().
     *
     * @dataProvider getRepositoriesDataProvider
     */
    public function testGetMultiple(PreferencesRepository $repository)
    {
        $repository->set('some.string', "a");
        $repository->set('some.int', 12);
        $repository->set('some.foo', "foo");

        $values = $repository->getMultiple(['some.string', 'some.int', 'non.existing']);

        self::assertTrue(\is_array($values));

        // Ensure keys are present.
        self::assertArrayHasKey('some.string', $values);
        self::assertArrayHasKey('some.int', $values);

        // Existing but non request keys.
        self::assertArrayNotHasKey('some.foo', $values);

        // Tricky one, driver could return the key associated with a null value.
        self::assertNull($values['non.existing'] ?? null);

        // And now, values.
        self::assertSame(12, $values['some.int']);
        self::assertSame("a", $values['some.string']);
    }

    /**
     * Test a simple getType().
     *
     * @dataProvider getRepositoriesDataProvider
     */
    public function testGetType(PreferencesRepository $repository)
    {
        $repository->set('some.string', "a");
        $type = $repository->getType('some.string');

        self::assertSame('string', $type->getNativeType());
        self::assertFalse($type->isCollection());
    }

    /**
     * Test getType() with collection.
     *
     * @dataProvider getRepositoriesDataProvider
     */
    public function testGetTypeWithCollection(PreferencesRepository $repository)
    {
        $repository->set('some.collection', ["a", "b"]);
        $type = $repository->getType('some.collection');

        self::assertSame('string', $type->getNativeType());
        self::assertTrue($type->isCollection());
    }

    /**
     * Test getType() with null return string.
     *
     * @dataProvider getRepositoriesDataProvider
     */
    public function testGetTypeWithNull(PreferencesRepository $repository)
    {
        $type = $repository->getType(\uniqid('non.existing.'));
        self::assertSame('string', $type->getNativeType());
        self::assertFalse($type->isCollection());
    }

    /**
     * YOLO mode for everyone.
     *
     * @dataProvider getRepositoriesDataProvider
     */
    public function testSetWillUpdateType(PreferencesRepository $repository)
    {
        $repository->set('some.string', "a");

        $type = $repository->getType('some.string');
        self::assertSame('string', $type->getNativeType());
        self::assertFalse($type->isCollection());

        $repository->set('some.string', [true, false]);

        $type = $repository->getType('some.string');
        self::assertSame('bool', $type->getNativeType());
        self::assertTrue($type->isCollection());
    }

    /**
     * Simple delete test.
     *
     * @dataProvider getRepositoriesDataProvider
     */
    public function testDelete(PreferencesRepository $repository)
    {
        self::assertFalse($repository->has('some.value'));

        $repository->set('some.value', 12);
        self::assertTrue($repository->has('some.value'));

        $repository->delete('some.value', 12);
        self::assertFalse($repository->has('some.value'));
        self::assertNull($repository->get('some.value'));
    }
}
