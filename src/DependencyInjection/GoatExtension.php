<?php

declare(strict_types=1);

namespace Goat\Bridge\Symfony\DependencyInjection;

use Doctrine\DBAL\Connection;
use Goat\Domain\Repository\RepositoryInterface;
use Goat\Query\QueryBuilder;
use Goat\Runner\Runner;
use Symfony\Bundle\WebProfilerBundle\WebProfilerBundle;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\Messenger\MessageBusInterface;

final class GoatExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = $this->getConfiguration($configs, $container);
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new YamlFileLoader($container, new FileLocator(\dirname(__DIR__).'/Resources/config'));
        $loader->load('query.yaml');

        $domainEnabled = \interface_exists(RepositoryInterface::class);
        $messengerEnabled = \interface_exists(MessageBusInterface::class);

        if ($domainEnabled) {
            $loader->load('domain.yaml');
            $this->processDomainIntegration($container);
        }
        if ($messengerEnabled) {
            $loader->load('messenger.yaml');
        }
        if ($messengerEnabled && $domainEnabled) {
            $loader->load('event.yaml');
        }

        $runnerDefinition = null;

        if (isset($config['runner']['driver'])) {
            switch ($config['runner']['driver']) {

                // @todo
                //   - we can't know for sure doctrine is enabled from here
                //   - we should be able to disable features depending upon other bundles
                case 'doctrine':
                    $runnerDefinition = (new Definition())
                        ->setClass(Runner::class)
                        ->setPublic(true)
                        ->setFactory([RunnerFactory::class, 'createFromDoctrineConnection'])
                        ->setArguments([new Reference(Connection::class), new Reference('goat.converter.default')])
                        ->addTag('container.hot_path')
                    ;
                    break;

                default:
                    // No need for a message, in theory Configuration component alread
                    // did handle that case for us.
                    throw new \InvalidArgumentException();
            }
        }

        if ($runnerDefinition) {

            // Create the query builder definition
            $queryBuilderDefinition = (new Definition())
                ->setClass(QueryBuilder::class)
                ->setShared(false)
                ->setPublic(true)
                ->setFactory([new Reference('goat.runner.default'), 'getQueryBuilder'])
                ->addTag('container.hot_path')
            ;

            // These are the 'default' runner and query builder
            // @todo support multiple connexions
            $container->addDefinitions([
                'goat.runner.default' => $runnerDefinition,
                'goat.query_builder.default' => $queryBuilderDefinition,
            ]);
            $container->addAliases([
                Runner::class => 'goat.runner.default',
                QueryBuilder::class => 'goat.query_builder.default',
            ]);
        }

        if (\in_array(WebProfilerBundle::class, $container->getParameter('kernel.bundles'))) {
            $loader->load('profiler.yml');
        }
    }

    /**
     * Integration with makinacorpus/goat-domain package.
     */
    private function processDomainIntegration(ContainerBuilder $container)
    {
        // @todo
    }

    /**
     * {@inheritdoc}
     */
    public function getConfiguration(array $config, ContainerBuilder $container)
    {
        return new GoatConfiguration();
    }
}
