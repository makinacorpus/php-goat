<?php

declare(strict_types=1);

namespace Goat\Bridge\Symfony;

use Goat\Bridge\Symfony\DependencyInjection\GoatExtension;
use Goat\Bridge\Symfony\DependencyInjection\Compiler\HydratorPass;
use Goat\Bridge\Symfony\DependencyInjection\Compiler\RegisterConverterPass;
use Goat\Domain\DependencyInjection\Compiler\DomainConfigurationPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
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
        $container->addCompilerPass(new HydratorPass());
        $container->addCompilerPass(new RegisterConverterPass());
        if (\class_exists(DomainConfigurationPass::class)) {
            $container->addCompilerPass(new DomainConfigurationPass());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getContainerExtension()
    {
        return new GoatExtension();
    }
}
