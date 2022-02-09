<?php

declare(strict_types=1);

namespace Goat\Dispatcher\Tests;

use MakinaCorpus\Message\BackwardCompat\AggregateMessage;
use MakinaCorpus\Message\BackwardCompat\AggregateMessageTrait;

class MockMessage implements AggregateMessage
{
    use AggregateMessageTrait;
}
