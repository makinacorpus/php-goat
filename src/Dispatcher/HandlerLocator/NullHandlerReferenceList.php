<?php

declare(strict_types=1);

namespace Goat\Dispatcher\HandlerLocator;

final class NullHandlerReferenceList implements HandlerReferenceList
{
    /**
     * {@inheritdoc}
     */
    public function first(string $className): ?HandlerReference
    {
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function all(string $className): iterable
    {
        return [];
    }
}
