<?php

declare(strict_types=1);

namespace Goat\Dispatcher\MessageDescriptor;

use Goat\Dispatcher\Message\MessageDescription;
use Goat\Dispatcher\Message\WithDescription;
use Goat\EventStore\Event;

/**
 * Attempt to describe messages whenever possible.
 *
 * This is a pure UI component, it doesn't have any business or technical
 * logic, just display logic.
 *
 * It is meant to tbe decorated for your own usage. You also may just extend
 * it and override the doReplaceVariable() method and place any kind of logic
 * you'd wish (load entities to display their properties, ...).
 */
class DefaultMessageDescriptor implements MessageDescriptor
{
    /**
     * Replace variable.
     */
    protected function doReplaceVariable(string $name, $value): string
    {
        return (string) $value;
    }

    /**
     * Process description with variables
     */
    private function process(MessageDescription $description): ?string
    {
        if (!$variables = $description->getVariables()) {
            return $description->getText();
        }

        // Can't array_map() here we need keys.
        foreach ($variables as $name => $value) {
            $variables[$name] = $this->replaceVariable($name, $value);
        }

        return \strtr($description->getText(), $variables);
    }

    /**
     * Describe event
     */
    private function doDescribe(WithDescription $event): ?string
    {
        return $this->process($event->describe());
    }

    /**
     * {@inheritdoc}
     */
    public function describe($message): ?string
    {
        if (\is_string($message)) {
            return $message;
        }

        if ($message instanceof MessageDescription) {
            return $this->process($message);
        }

        if ($message instanceof WithDescription) {
            return $this->doDescribe($message);
        }

        if ($message instanceof Event) {
            if (($data = $message->getMessage()) instanceof WithDescription) {
                return $this->doDescribe($data);
            }
        }

        return null;
    }
}
