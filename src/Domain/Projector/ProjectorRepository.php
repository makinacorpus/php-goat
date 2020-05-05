<?php

declare(strict_types=1);

namespace Goat\Domain\Projector;

class ProjectorRepository
{
    /** @var Projector[] */
    private $projectors = [];

    /**
     * Find a projector by identifier.
     */
    public function findByIdentifier(string $identifier): ?Projector
    {
        if ($this->projectors) {
            foreach ($this->projectors as $projector) {
                if ($identifier === $projector->identifier) {
                    return $projector;
                }
            }
        }

        return null;
    }

    /**
     * Find a projector by class name.
     */
    public function findByClassName(string $className): ?Projector
    {
        if ($this->projectors) {
            foreach ($this->projectors as $projector) {
                if ($className === $projector::class) {
                    return $projector;
                }
            }
        }

        return null;
    }

    /**
     * Find a projector by identifier or by class name.
     */
    public function findByIdentifierOrClassName(string $identifierOrClassName): ?Projector
    {
        if ( !$projector = $this->findByIdentifier($identifierOrClassName)) {
            $projector = $this->findByIdentifier($identifierOrClassName);
        }

        return $projector;
    }

    public function setProjectors(iterable $projectors): void
    {
        $this->projectors = $projectors;
    }
}
