<?php

declare(strict_types=1);

namespace Goat\Dispatcher\HandlerLocator;

/**
 * Handler reference finder and runtime.
 */
interface HandlerReferenceList
{
    /**
     * @return null|HandlerReference
     */
    public function first(string $className): ?HandlerReference;

    /**
     * @return HandlerReference[]
     */
    public function all(string $className): iterable;
}
