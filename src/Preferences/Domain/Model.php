<?php

declare(strict_types=1);

namespace Goat\Preferences\Domain\Model;

/**
 * Necessary information for validating an incomming value
 */
interface ValueType
{
    /**
     * Get PHP native type for value
     */
    public function getNativeType(): string;

    /**
     * Is this value an enum
     */
    public function isEnum(): bool;

    /**
     * Get allowed values, if enum
     *
     * @return string[]
     */
    public function getAllowedValues(): array;

    /**
     * Is this type a collection
     */
    public function isCollection(): bool;

    /**
     * Is this collection indexed with strings
     */
    public function isHashMap(): bool;
}

/**
 * More descriptive information about a value
 */
interface ValueSchema extends ValueType
{
    /**
     * Value name
     */
    public function getName(): string;

    /**
     * Get value short description
     */
    public function getLabel(): ?string;

    /**
     * Get complete value description
     */
    public function getDescription(): ?string;

    /**
     * Get default value if any
     */
    public function getDefault();

    /**
     * Does it has a default value (null is not a value)
     */
    public function hasDefault(): bool;
}

/**
 * Value type trait
 */
trait ValueTypeTrait
{
    /** @var string */
    private $nativeType;

    /** @var bool */
    private $enum = false;

    /** @var array */
    private $allowedValues = [];

    /** @var bool */
    private $collection = false;

    /** @var bool */
    private $hashMap = false;

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

/**
 * Default value type implementation
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

/**
 * Default value schema implementation
 */
final class DefaultValueSchema implements ValueSchema
{
    use ValueTypeTrait;

    /** @var string */
    private $name;

    /** @var ?string */
    private $label;

    /** @var ?string */
    private $description;

    /** @var mixed */
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

/**
 * Validates values
 */
final class ValueValidator
{
    /**
     * Alias of PHP \gettype() which gives you correct types, but fallbacks null
     * to be 'string', because 'string' is most liberal variable type.
     */
    private static function guessTypeOf($value): string
    {
        if (null === $value) {
            return 'string';
        }
        if (\is_object($value)) {
            return \get_class($value);
        }
        $type = \gettype($value);
        if ('boolean' === $type) {
            return 'bool';
        }
        if ('integer' === $type) {
            return 'int';
        }
        if ('double' === $type) {
            return 'float';
        }
        return $type;
    }

    /**
     * Validate a single value, coerce type if necessary.
     */
    private static function validateSingle(ValueType $type, $value)
    {
        if (null === $value) {
            return null; // Always allow null
        }
        if (\is_resource($value)) {
            throw new \InvalidArgumentException("Value cannot be a resource");
        }
        if ($value instanceof \Closure) {
            throw new \InvalidArgumentException("Value cannot be a closure/function/generator");
        }

        if ($type->isEnum() && ($allowed = $type->getAllowedValues())) {
            if (!\in_array($value, $allowed)) {
                throw new \InvalidArgumentException(\sprintf("Value is not one of '%s'", \implode("', '", $allowed)));
            }
            return $value; // No need to check further if we found the value
        }

        if (($expected = $type->getNativeType()) !== ($valueType = self::guessTypeOf($value))) {
            throw new \InvalidArgumentException(\sprintf("Value type mismatch, expected: '%s', got: '%s'", $expected, $valueType));
        }
        return $value;
    }

    /**
     * Arbitrarily find type of value
     */
    public static function getTypeOf($value): ValueType
    {
        if (null === $value) {
            return new DefaultValueType('string');
        }
        if (\is_array($value)) {
            if ($value) {
                $first = \reset($value);
                return new DefaultValueType(self::guessTypeOf($first), true);
            }
            return new DefaultValueType('string', true);
        }
        return new DefaultValueType(self::guessTypeOf($value), \is_array($value));
    }

    /**
     * Validate value, coerce type if necessary.
     */
    public static function validate(ValueType $type, $value)
    {
        // Allow collection values be passed as a single value, hence the
        // (array) cast. Ideally it should be iterables but for the
        // sake of simplicity and because \array_map() only deals with
        // arrays, we choose arrays.
        if ($type->isCollection()) {
            return \array_map(
                static function ($value) use ($type) {
                    return self::validateSingle($type, $value);
                },
                (array)$value
            );
        }
        return self::validateSingle($type, $value);
    }
}
