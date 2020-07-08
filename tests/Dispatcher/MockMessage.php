<?php

declare(strict_types=1);

namespace Goat\Dispatcher\Tests;

use Goat\Dispatcher\Message\Message;
use Goat\Dispatcher\Message\MessageTrait;

class MockMessage implements Message
{
    use MessageTrait;
}
