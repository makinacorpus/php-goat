<?php

declare(strict_types=1);

namespace Goat\Domain\Tests\Event;

use Goat\Domain\Event\RetryableMessage;

class MockRetryableMessage extends MockMessage implements RetryableMessage
{
}
