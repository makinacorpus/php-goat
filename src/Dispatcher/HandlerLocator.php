<?php

declare(strict_types=1);

namespace Goat\Dispatcher;

use Goat\Dispatcher\Error\HandlerNotFoundError;

interface  HandlerLocator
{
    /**
     * Find handler for given message.
     *
     * @throws HandlerNotFoundError
     */
    public function find($message): callable;
}
