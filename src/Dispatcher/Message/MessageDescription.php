<?php

declare(strict_types=1);

namespace Goat\Dispatcher\Message;

/**
 * Formatted message description.
 */
class MessageDescription
{
    private string $text;
    /** @var mixed[] */
    private array $variables = [];

    public function __construct(string $text, array $variables = [])
    {
        $this->text = $text;
        $this->variables = $variables;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function getVariables(): array
    {
        return $this->variables;
    }

    public function format(): string
    {
        return \strtr($this->text, $this->variables);
    }

    /**
     * self::format() alias/
     */
    public function __toString()
    {
        return $this->format();
    }
}
