<?php

declare(strict_types=1);

namespace Goat\Preferences\Domain\Model;

/**
 * Value type trait.
 */
trait ValueTypeTrait
{
    private string $nativeType;
    private bool $enum = false;
    /** @var mixed[] */
    private array $allowedValues = [];
    private bool $collection = false;
    private bool $hashMap = false;

    public function getNativeType(): string
    {
        return $this->nativeType;
    }

    public function isEnum(): bool
    {
        return $this->enum;
    }

    public function getAllowedValues(): array
    {
        return $this->allowedValues;
    }

    public function isCollection(): bool
    {
        return $this->collection;
    }

    public function isHashMap(): bool
    {
        return $this->hashMap;
    }
}
