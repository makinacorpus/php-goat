<?php

declare(strict_types=1);

namespace Goat\Preferences\Domain\Message;

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
