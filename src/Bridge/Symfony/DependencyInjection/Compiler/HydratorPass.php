<?php

declare(strict_types=1);

namespace Goat\Bridge\Symfony\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;

final class HydratorPass implements CompilerPassInterface
{
    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        // @todo multiple connexions?
        if ($container->has('goat.hydrator_map') && $container->has('goat.runner.default')) {
            $container
                ->getDefinition('goat.runner.default')
                ->addMethodCall('setHydratorMap', [new Reference('goat.hydrator_map')])
            ;
        }
    }
}
