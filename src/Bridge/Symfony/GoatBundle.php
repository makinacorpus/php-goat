<?php

declare(strict_types=1);

namespace Goat\Bridge\Symfony;

use Goat\Bridge\Symfony\DependencyInjection\GoatExtension;
use Goat\Bridge\Symfony\DependencyInjection\Compiler\DomainConfigurationPass;
use Goat\Bridge\Symfony\DependencyInjection\Compiler\HandlerLocatorRegistrationPass;
use Goat\Bridge\Symfony\DependencyInjection\Compiler\MonologConfigurationPass;
use Goat\Bridge\Symfony\DependencyInjection\Compiler\RepositoryRegistryRegisterPass;
use Goat\Domain\Repository\RepositoryInterface;
use Goat\Domain\Repository\Registry\RepositoryRegistry;
use Goat\Domain\Repository\Registry\RepositoryRegistryAware;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
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
        $container->addCompilerPass(new DomainConfigurationPass());
        $container->addCompilerPass(new HandlerLocatorRegistrationPass());
        $container->addCompilerPass(new MonologConfigurationPass());

        // Repository registry magic.
        $container->addCompilerPass(new RepositoryRegistryRegisterPass());
        $container
            ->registerForAutoconfiguration(RepositoryRegistryAware::class)
            ->addTag('goat.domain.repository.registry.aware')
        ;
        $container
            ->registerForAutoconfiguration(RepositoryInterface::class)
            ->addTag('goat.domain.repository')
        ;
        $container
            ->registerForAutoconfiguration(RepositoryInterface::class)
            ->addMethodCall('setRepositoryRegistry', [new Reference(RepositoryRegistry::class)])
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function getContainerExtension(): ?ExtensionInterface
    {
        return new GoatExtension();
    }
}
