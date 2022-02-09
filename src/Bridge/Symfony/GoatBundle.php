<?php

declare(strict_types=1);

namespace Goat\Bridge\Symfony;

use Goat\Bridge\Symfony\DependencyInjection\GoatExtension;
use Goat\Bridge\Symfony\DependencyInjection\Compiler\HandlerLocatorRegistrationPass;
use Goat\Bridge\Symfony\DependencyInjection\Compiler\MonologConfigurationPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * @codeCoverageIgnore
 */
final class GoatBundle extends Bundle
{
    /**
     * {@inheritdoc}
     */
    public function build(ContainerBuilder $container)
    {
        $container->addCompilerPass(new HandlerLocatorRegistrationPass());
        $container->addCompilerPass(new MonologConfigurationPass());
    }

    /**
     * {@inheritdoc}
     */
    public function getContainerExtension(): ?ExtensionInterface
    {
        return new GoatExtension();
    }
}
