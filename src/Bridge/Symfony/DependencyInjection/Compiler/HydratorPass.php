<?php

declare(strict_types=1);

namespace Goat\Bridge\Symfony\DependencyInjection\Compiler;

use Goat\Bridge\Symfony\GeneratedHydrator\GeneratedHydratorMap;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;

final class HydratorPass implements CompilerPassInterface
{
    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        // Just replace the dummy service with the real one.
        if ($container->has('generated_hydrator')) {
            $container->setDefinition(
                'goat.hydrator_map',
                (new Definition())
                    ->setClass(GeneratedHydratorMap::class)
                    ->setArguments([new Reference('generated_hydrator')])
            );
        }

        // @todo multiple connexions?
        if ($container->hasDefinition('goat.hydrator_map') && $container->hasDefinition('goat.runner.default')) {
            $container
                ->getDefinition('goat.runner.default')
                ->addMethodCall('setHydratorMap', [new Reference('goat.hydrator_map')])
            ;
        }
    }
}
