<?php

declare(strict_types=1);

namespace Goat\Hydrator;

/**
 * @internal
 * @deprecated
 *   For backward compatibility. Original class was final, so we can safely
 *   convert it to an interface, nobody will ever have overrided it.
 */
interface HydratorMap
{
    /**
     * Is this class supported
     */
    public function supportsClass(string $class): bool;

    /**
     * Get hydrator for class or identifier
     *
     * @param string $class
     *   Either a class name or a class alias
     * @param string $separator
     *   Separator for the hierarchical hydrator
     *
     * @return HydratorInterface
     *   Returned instance will not be shared
     */
    public function get(string $class, string $separator = null): HydratorInterface;
}
