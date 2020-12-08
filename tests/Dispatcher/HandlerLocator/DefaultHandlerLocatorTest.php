<?php

declare(strict_types=1);

namespace Goat\Dispatcher\Tests\HandlerLocator;

use Goat\Dispatcher\Error\HandlerNotFoundError;
use Goat\Dispatcher\HandlerLocator\DefaultHandlerLocator;
use Goat\Dispatcher\HandlerLocator\DefaultHandlerReferenceList;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;

final class DefaultHandlerLocatorTest extends TestCase
{
    public function testFind(): void
    {
        $container = new Container();
        $container->set('mock_handler', new MockHandler());

        $referenceList = new DefaultHandlerReferenceList(null, false);
        $referenceList->appendFromClass(MockHandler::class, 'mock_handler');
        $locator = new DefaultHandlerLocator($referenceList);
        $locator->setContainer($container);

        $commandA = new MockCommandA();
        $callback = $locator->find($commandA);
        self::assertFalse($commandA->done);
        self::assertNotNull($callback);

        $callback($commandA);
        self::assertTrue($commandA->done);

        $commandB = new MockCommandB();
        $callback = $locator->find($commandB);
        self::assertFalse($commandB->done);
        self::assertNotNull($callback);

        $callback($commandB);
        self::assertTrue($commandB->done);

        self::expectException(HandlerNotFoundError::class);
        $locator->find(new MockCommandC());
    }
}
