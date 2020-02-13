<?php

declare(strict_types=1);

namespace Goat\Bridge\Symfony\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;

final class MonologConfigurationPass implements CompilerPassInterface
{
    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        // We set our own formatter conditionnaly, here we replace the default
        // one using ours instead.
        // @see \Goat\Bridge\Symfony\DependencyInjection\GoatExtension::configureMonolog()
        if ($container->hasDefinition('goat.monolog.formatter.line')) {
            if ($container->hasDefinition('monolog.formatter.line')) {
                $container->removeDefinition('monolog.formatter.line');
            }
            if ($container->hasAlias('monolog.formatter.line')) {
                $container->removeAlias('monolog.formatter.line');
            }
            $container->setAlias('monolog.formatter.line', 'goat.monolog.formatter.line');
        }
    }
}
