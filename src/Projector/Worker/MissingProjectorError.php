<?php

declare(strict_types=1);

namespace Goat\Projector\Worker;

use Goat\Projector\ProjectorError;

class MissingProjectorError extends \InvalidArgumentException implements ProjectorError
{
}
