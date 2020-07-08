<?php

declare(strict_types=1);

namespace Goat\Dispatcher\Message;

/**
 * Self-decribing message, used for user interface display.
 */
interface WithDescription
{
    /**
     * Describe what happens.
     *
     * Uses an intermediate text representation with EventDescription class
     * which allows displaying code to proceed to variable replacement if
     * necessary. For exemple, it may allow to replace a user identifier
     * with the user full name.
     *
     * How and when replacement will be done is up to each project.
     *
     * For use with Symfony translator component, you should adopt the
     * convention of naming variables using "%" prefix and suffix, for example:
     *
     * @code
     * <?php
     *
     * namespace App\Dispatcher;
     *
     * use Goat\Dispatcher\Message\MessageDescription
     * use Goat\Dispatcher\Message\WithDescription
     *
     * final class FooEvent implements WithDescription
     * {
     *     private string $userId;
     *
     *     public static function create(string $userId): self
     *     {
     *         $ret = new self;
     *         $ret->userId = $userId;
     *         return $ret;
     *     }
     *
     *     public function describe(): EventDescription
     *     {
     *         return new EventDescription("%user% said "Foo".", [
     *             "%user%" => $this->userId,
     *         ]);
     *     }
     * }
     * @endcode
     */
    public function describe(): MessageDescription;
}
