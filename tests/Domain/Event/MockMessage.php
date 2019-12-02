<?php

declare(strict_types=1);

namespace Goat\Domain\Tests\Event;

use Goat\Domain\Event\Message;
use Goat\Domain\Event\MessageTrait;

class MockMessage implements Message
{
    use MessageTrait;
}
