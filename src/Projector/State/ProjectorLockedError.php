<?php

declare(strict_types=1);

namespace Goat\Projector\State;

use Goat\Projector\ProjectorError;

class ProjectorLockedError extends \InvalidArgumentException implements ProjectorError
{
    public function __construct(string $id)
    {
        parent::__construct(\sprintf("Projector '%s' is already locked.", $id));
    }
}
