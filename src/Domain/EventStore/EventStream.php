<?php

declare(strict_types=1);

namespace Goat\Domain\EventStore;

/**
 * @var \Goat\Domain\Event\Event[]
 */
interface EventStream extends \Traversable, \Countable
{
}
