<?php

declare(strict_types=1);

namespace Goat\Preferences\Domain\Model;

/**
 * Validates values.
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
            return null; // Always allow null.
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
            return $value; // No need to check further if we found the value.
        }

        if (($expected = $type->getNativeType()) !== ($valueType = self::guessTypeOf($value))) {
            throw new \InvalidArgumentException(\sprintf("Value type mismatch, expected: '%s', got: '%s'", $expected, $valueType));
        }
        return $value;
    }

    /**
     * Arbitrarily find type of value.
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
                fn ($value) => self::validateSingle($type, $value),
                (array)$value
            );
        }
        return self::validateSingle($type, $value);
    }
}
