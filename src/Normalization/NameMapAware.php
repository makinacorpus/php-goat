<?php

declare(strict_types=1);

namespace Goat\Normalization;

interface NameMapAware
{
    /**
     * Get or create empty namespace map.
     *
     * @internal
     */
    public function getNameMap(): NameMap;

    /**
     * {@inheritdoc}
     */
    public function setNameMap(NameMap $nameMap): void;
}
