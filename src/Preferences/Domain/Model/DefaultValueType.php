<?php

declare(strict_types=1);

namespace Goat\Preferences\Domain\Model;

/**
 * Default value type implementation.
 */
final class DefaultValueType implements ValueType
{
    use ValueTypeTrait;

    public function __construct(
        string $nativeType,
        bool $collection = false,
        ?array $allowedValues = null,
        bool $hashMap = false)
    {
        $this->nativeType = $nativeType;
        $this->collection = $collection;

        if ($allowedValues) {
            // It can only be an enum if it has allowed values.
            $this->allowedValues = $allowedValues;
            $this->enum = true;
        } else if ($collection) {
            // It cannot be a hashmap if there's allowed values.
            $this->hashMap = $hashMap;
        }
    }
}
