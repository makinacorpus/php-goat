<?php

declare(strict_types=1);

namespace Goat\Preferences\Domain\Model;

/**
 * Default value schema implementation.
 */
final class DefaultValueSchema implements ValueSchema
{
    use ValueTypeTrait;

    private string $name;
    private ?string $label = null;
    private ?string $description = null;
    private $default;

    /**
     * Create instance from array
     *
     * @param array $data
     *   May contain any of the following data (all are optional):
     *      - 'name' (string, mandatory): value name
     *      - 'type' (string, default is "string"): value type
     *      - 'default' (anything, default is null): default value
     *      - 'collection' (boolean, default is false): multiple values allowed
     *      - 'hashmap' (boolean, default is false): values can have keys
     *      - 'allowed_values': (array of anything, default is null) allowed values
     *      - 'label' (string, default is null): value label
     *      - 'description (string, default null): value string
     */
    public static function fromArray(array $data): self
    {
        if (!isset($data['name'])) {
            throw new \InvalidArgumentException("'name' key is mandatory for schema values");
        }

        $ret = new self();
        $ret->collection = $collection = (bool)($data['collection'] ?? false);
        $ret->default = $data['default'] ?? null;
        $ret->description = $data['description'] ?? null;
        $ret->label = $data['label'] ?? null;
        $ret->name = $data['name'] ?? null;
        $ret->nativeType = (string)($data['type'] ?? 'string');

        $allowedValues = $data['allowed_values'] ?? null;
        if ($allowedValues) {
            // It can only be an enum if it has allowed values.
            $ret->allowedValues = $allowedValues;
            $ret->enum = true;
        } else if ($collection) {
            // It cannot be a hashmap if there's allowed values.
            $ret->hashMap = (bool)($data['hashmap'] ?? false);
        }

        return $ret;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getDefault()
    {
        return $this->default;
    }

    public function hasDefault(): bool
    {
        return null !== $this->default;
    }
}
