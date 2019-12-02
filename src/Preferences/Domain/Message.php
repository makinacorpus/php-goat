<?php

declare(strict_types=1);

namespace Goat\Preferences\Domain\Message;

/**
 * Basics for preference messages
 *
 * @codeCoverageIgnore
 */
trait PreferenceValueMessageTrait
{
    /** @var string */
    private $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function getName(): string
    {
        return $this->name;
    }
}

/**
 * Set configuration value
 *
 * [group=settings]
 *
 * @codeCoverageIgnore
 */
final class PreferenceValueSet
{
    use PreferenceValueMessageTrait;

    /** @var mixed */
    private $value;

    public function __construct(string $name, $value)
    {
        $this->name = $name;
        $this->value = $value;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getValue()
    {
        return $this->value;
    }
}

/**
 * Set many configuration value
 *
 * 'values' property is a dictionnary, keys are preferences names, values
 * are arbitrary values whose type will depend upon the schema.
 *
 * [group=settings]
 *
 * @codeCoverageIgnore
 */
final class PreferenceValueSetMany
{
    /** @var mixed[] */
    private $values;

    /**
     * @param array $values
     *   Keys are preference names, values are values
     */
    public function __construct(array $values)
    {
        $this->values = $values;
    }

    public function getValueList()
    {
        return $this->values;
    }
}

/**
 * Delete configuration value
 *
 * [group=settings]
 *
 * @codeCoverageIgnore
 */
final class PreferenceValueDelete
{
    use PreferenceValueMessageTrait;
}
