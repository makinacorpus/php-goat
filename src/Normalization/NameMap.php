<?php

declare(strict_types=1);

namespace Goat\Normalization;

/**
 * Extends the type map and provide the reverse operation.
 */
interface NameMap extends TypeMap
{
    /**
     * From message instance guess message name (normalized)
     */
    public function getMessageName($message): string;

    /**
     * From message name to PHP native type (denormalization)
     */
    public function getName(string $type): string;
}
