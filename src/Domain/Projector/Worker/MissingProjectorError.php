<?php

declare(strict_types=1);

namespace Goat\Domain\Projector\Worker;

use Goat\Domain\Projector\ProjectorError;

class MissingProjectorError extends \InvalidArgumentException implements ProjectorError
{
}
