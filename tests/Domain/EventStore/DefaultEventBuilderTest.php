<?php

declare(strict_types=1);

namespace Goat\Domain\Tests\EventStore;

use Goat\Domain\EventStore\DefaultEventBuilder;
use Goat\Domain\Tests\Event\MockMessage;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class DefaultEventBuilderTest extends TestCase
{
    public function testMessage(): void
    {
        $builder = new DefaultEventBuilder(fn () => null);

        $message = new MockMessage();

        $builder->message($message, 'foo.bar');

        self::assertSame($message, $builder->getMessage());
        self::assertSame('foo.bar', $builder->getMessageName());
    }

    public function testAggregate(): void
    {
        $builder = new DefaultEventBuilder(fn () => null);

        $id = Uuid::uuid4();

        $builder->aggregate('bar.baz', $id);

        self::assertSame('bar.baz', $builder->getAggregateType());
        self::assertTrue($id->equals($builder->getAggregateId()));

        $builder->aggregate('pouf', null);

        self::assertSame('pouf', $builder->getAggregateType());
        self::assertNull($builder->getAggregateId());
    }

    public function testProperty(): void
    {
        $builder = new DefaultEventBuilder(fn () => null);

        $builder->property('foo', 'This is foo.');
        $builder->property('bar', 'This is bar.');

        self::assertSame(
            [
                'foo' => 'This is foo.',
                'bar' => 'This is bar.',
            ],
            $builder->getProperties()
        );
    }

    public function testPropertyWithNullRemovesProperty(): void
    {
        $builder = new DefaultEventBuilder(fn () => null);

        $builder->property('foo', 'This is foo.');

        self::assertSame(
            [
                'foo' => 'This is foo.',
            ],
            $builder->getProperties()
        );

        $builder->property('foo', null);

        self::assertSame([], $builder->getProperties());
    }

    public function testSetWhenLockedRaiseError(): void
    {
        $builder = new DefaultEventBuilder(fn () => null);

        $builder->execute();

        self::expectException(\BadMethodCallException::class);
        $builder->aggregate('foo');
    }

    public function testGetMessageWhenNotSetFails(): void
    {
        $builder = new DefaultEventBuilder(fn () => null);

        self::expectException(\BadMethodCallException::class);
        $builder->getMessage();
    }

    public function testExecuteCallsConstructorCallable(): void
    {
        $builder = new DefaultEventBuilder(fn (DefaultEventBuilder $builder) => 'Foo, tout simplement.');

        $result = $builder->execute();

        self::assertSame('Foo, tout simplement.', $result);
    }
}
