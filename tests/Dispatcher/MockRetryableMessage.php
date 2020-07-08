<?php

declare(strict_types=1);

namespace Goat\Dispatcher\Tests;

use Goat\Dispatcher\Message\RetryableMessage;

class MockRetryableMessage extends MockMessage implements RetryableMessage
{
}
