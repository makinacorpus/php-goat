<?php

declare(strict_types=1);

namespace Goat\Domain\Projector;

class ProjectorRegistry
{
    /** @var Projector[] */
    private ?iterable $projectors = null;

    /**
     * Get all projectors.
     */
    public function getAll(): iterable
    {
        $this->isInitializedOrDie();

        return $this->projectors;
    }

    /**
     * Get all runtime-enabled projectors.
     */
    public function getEnabled(): iterable
    {
        foreach ($this->getAll() as $projector) {
            if (!$projector instanceof NoRuntimeProjector) {
                yield $projector;
            }
        }
    }

    /**
     * Find a projector by identifier.
     */
    protected function findByIdentifier(string $id, bool $throwExceptionOnMissing = true): ?Projector
    {
        $this->isInitializedOrDie();

        if ($this->projectors) {
            foreach ($this->projectors as $projector) {
                if ($id === $projector->getIdentifier()) {
                    return $projector;
                }
            }
        }

        if ($throwExceptionOnMissing) {
            throw new ProjectorDoesNotExistError($id);
        }

        return null;
    }

    /**
     * Find a projector by class name.
     */
    protected function findByClassName(string $className, bool $throwExceptionOnMissing = true): ?Projector
    {
        $this->isInitializedOrDie();

        if ($this->projectors) {
            foreach ($this->projectors as $projector) {
                if ($className === \get_class($projector)) {
                    return $projector;
                }
            }
        }

        if ($throwExceptionOnMissing) {
            throw new ProjectorDoesNotExistError($className);
        }

        return null;
    }

    /**
     * Find a projector by identifier or by class name.
     */
    public function find(string $identifierOrClassName, bool $throwExceptionOnMissing = true): ?Projector
    {
        if ( $projector = $this->findByIdentifier($identifierOrClassName, false)) {
            return $projector;
        } elseif ($projector = $this->findByClassName($identifierOrClassName, false)) {
            return $projector;
        }

        if ($throwExceptionOnMissing) {
            throw new ProjectorDoesNotExistError($identifierOrClassName);
        }

        return null;
    }

    public function setProjectors(iterable $projectors): void
    {
        if (null !== $this->projectors) {
            throw new \BadMethodCallException("Projector registry cannot be initialized twice.");
        }

        $this->projectors = $projectors;
    }

    private function isInitializedOrDie()
    {
        if (null === $this->projectors) {
            throw new \BadMethodCallException("Projector registry has not been initialized yet.");
        }
    }
}
