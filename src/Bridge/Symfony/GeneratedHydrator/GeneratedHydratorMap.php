<?php

declare(strict_types=1);

namespace Goat\Bridge\Symfony\GeneratedHydrator;

use GeneratedHydrator\Bridge\Symfony\Hydrator;
use Goat\Hydrator\HydratorInterface;
use Goat\Hydrator\HydratorMap;

/**
 * Make use of makinacorpus/generated-hydrator-bundle.
 *
 * @deprecated
 */
final class GeneratedHydratorMap implements HydratorMap
{
    /** @var Hydrator */
    private $hydrator;

    /**
     * Default constructor
     */
    public function __construct(Hydrator $hydrator)
    {
        $this->hydrator = $hydrator;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsClass(string $className): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $className, string $separator = null): HydratorInterface
    {
        return new class ($className, $this->hydrator) implements HydratorInterface
        {
            /** @var string */
            private $className;

            /** @var Hydrator */
            private $hydrator;

            public function __construct(string $className, Hydrator $hydrator)
            {
                $this->className = $className;
                $this->hydrator = $hydrator;
            }

            public function createAndHydrateInstance(array $values)
            {
                return $this->hydrator->createAndHydrate($this->className, $values);
            }

            public function hydrateObject(array $values, $object)
            {
                return $this->hydrator->hydrate($object, $values);
            }

            public function extractValues($object)
            {
                return $this->hydrator->extract($object);
            }
        };
    }
}
