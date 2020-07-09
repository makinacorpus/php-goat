<?php

declare(strict_types=1);

namespace Goat\Dispatcher;

/**
 * Implement this interface on any class, all of its methods will be
 * introspected for being used as message handler.
 *
 * If you are using Symfony, register those handlers by applying the
 * 'goat.message_handler' tag on it.
 *
 * A suitable method is:
 *   - a public method,
 *   - with one and only one parameter,
 *   - parameter must be a class, any class.
 *
 * If you apply this interface on classes that have methods that correspond
 * to the above description who are not handlers, it will create false
 * positive handlers being registered, but it will not cause any bug.
 */
interface Handler
{
}
