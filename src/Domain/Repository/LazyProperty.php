<?php

declare(strict_types=1);

namespace Goat\Domain\Repository;

interface LazyProperty
{
    /**
     * @param callable|iterable $initializer
     */
    public function __construct($initializer);

    /**
     * Get the real value behind
     */
    public function unwrap();
}
