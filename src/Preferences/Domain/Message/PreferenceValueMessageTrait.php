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
    private string $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
