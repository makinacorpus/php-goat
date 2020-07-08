<?php

declare(strict_types=1);

namespace Goat\EventStore\Tests;

use Goat\Dispatcher\Tests\MockMessage;
use Goat\EventStore\DefaultEventBuilder;
use Goat\EventStore\Event;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class DefaultEventBuilderTest extends TestCase
{
    public function testMessage(): void
    {
        $builder = new DefaultEventBuilder(fn () => Event::create(new MockMessage()));

        $message = new MockMessage();

        $builder->message($message, 'foo.bar');
        $builder->name('foo.bar');

        self::assertSame($message, $builder->getMessage());
        self::assertSame('foo.bar', $builder->getMessageName());
    }

    public function testAggregate(): void
    {
        $builder = new DefaultEventBuilder(fn () => Event::create(new MockMessage()));

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
        $builder = new DefaultEventBuilder(fn () => Event::create(new MockMessage()));

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

    public function testProperties(): void
    {
        $builder = new DefaultEventBuilder(fn () => Event::create(new MockMessage()));

        $builder->properties([
            'foo' => 'This is foo.',
            'bar' => 'This is bar.',
        ]);

        self::assertSame(
            [
                'foo' => 'This is foo.',
                'bar' => 'This is bar.',
            ],
            $builder->getProperties()
        );
    }

    public function testSetWhenLockedRaiseError(): void
    {
        $builder = new DefaultEventBuilder(fn () => Event::create(new MockMessage()));

        $builder->execute();

        self::expectException(\BadMethodCallException::class);
        $builder->aggregate('foo');
    }

    public function testGetMessageWhenNotSetFails(): void
    {
        $builder = new DefaultEventBuilder(fn () => Event::create(new MockMessage()));

        self::expectException(\BadMethodCallException::class);
        $builder->getMessage();
    }

    public function testExecuteCallsConstructorCallable(): void
    {
        $event = Event::create(new MockMessage());
        $builder = new DefaultEventBuilder(fn (DefaultEventBuilder $builder) => $event);

        $result = $builder->execute();

        self::assertSame($event, $result);
    }
}
