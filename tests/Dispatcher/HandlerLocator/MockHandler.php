<?php

declare(strict_types=1);

namespace Goat\Dispatcher\Tests\HandlerLocator;

use Goat\Dispatcher\Handler;

final class MockHandler implements Handler
{
    /**
     * Cannot use when more than one parameter.
     *
     * @codeCoverageIgnore
     */
    public function doNotA(MockCommandA $command, int $foo): void
    {
        throw new \BadMethodCallException("I shall not be called.");
    }

    /**
     * Method with builtin parameters to be ignored.
     *
     * @codeCoverageIgnore
     */
    public function doNotBuiltin(string $command): void
    {
        throw new \BadMethodCallException("I shall not be called.");
    }

    /**
     * Method with builtin parameters that yield no type.
     *
     * @codeCoverageIgnore
     */
    public function doNotNoType($command): void
    {
        throw new \BadMethodCallException("I shall not be called.");
    }

    /**
     * Static method will be ignored.
     *
     * @codeCoverageIgnore
     */
    public static function doNotStatic(): void
    {
        throw new \BadMethodCallException("I shall not be called.");
    }

    /**
     * OK.
     */
    public function doA(MockCommandA $command): void
    {
        $command->done = true;
    }

    /**
     * Cannot use when no or wrong type hinting.
     *
     * @codeCoverageIgnore
     */
    public function doNotB(MockEventA $command): void
    {
        throw new \BadMethodCallException("I shall not be called.");
    }

    /**
     * OK.
     */
    public function doB(MockCommandB $command): void
    {
        $command->done = true;
    }
}
