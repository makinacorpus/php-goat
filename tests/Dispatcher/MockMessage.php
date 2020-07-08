<?php

declare(strict_types=1);

namespace Goat\Dispatcher\Tests;

use Goat\Dispatcher\Message;
use Goat\Dispatcher\MessageTrait;

class MockMessage implements Message
{
    use MessageTrait;
}
