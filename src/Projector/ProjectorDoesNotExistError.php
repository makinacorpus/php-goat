<?php

declare(strict_types=1);

namespace Goat\Projector;

class ProjectorDoesNotExistError extends \InvalidArgumentException implements ProjectorError
{
    public function __construct(string $id)
    {
        parent::__construct(\sprintf("Projector with class or identifier '%s' does not exist", $id));
    }
}
