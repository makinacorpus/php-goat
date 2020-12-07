<?php

declare(strict_types=1);

namespace Goat\Normalization;

trait NameMapAwareTrait /* implements NameMapAware */
{
    private ?NameMap $nameMap = null;

    /**
     * {@inheritdoc}
     */
    public function getNameMap(): NameMap
    {
        return $this->nameMap ?? ($this->nameMap = new DefaultNameMap());
    }

    /**
     * {@inheritdoc}
     */
    public function setNameMap(NameMap $nameMap): void
    {
        $this->nameMap = $nameMap;
    }
}
