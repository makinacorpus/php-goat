<?php

declare(strict_types=1);

namespace Goat\Hydrator;

/**
 * @internal
 * @deprecated
 *   For backward compatibility.
 */
final class DefaultHydratorMap implements HydratorMap
{
    private $classBlacklist = [];
    private $configurations = [];
    private $customHydrators = [];
    private $realHydrators = [];

    /**
     * {@inheritdoc}
     */
    public function supportsClass(string $class): bool
    {
        return !\in_array($class, $this->classBlacklist) && \class_exists($class);
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $class, string $separator = null): HydratorInterface
    {
        if ($separator) {
            throw new \RuntimeException("Separator usage is not implemented yet.");
        }
        if ($this->supportsClass($class)) {
            throw new \RuntimeException(\sprintf("Class '%s' is not supported", $class));
        }
        if (!isset($this->customHydrators[$class])) {
            throw new \RuntimeException("Hydrators must be manually registered.");
        }
        return $this->customHydrators[$class];
    }
}
