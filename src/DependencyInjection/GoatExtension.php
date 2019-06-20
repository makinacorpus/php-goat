<?php

declare(strict_types=1);

namespace Goat\Bridge\Symfony\DependencyInjection;

use Doctrine\DBAL\Connection;
use Goat\Domain\Repository\RepositoryInterface;
use Goat\Query\QueryBuilder;
use Goat\Runner\Runner;
use Goat\Runner\Metadata\ApcuResultMetadataCache;
use Symfony\Bundle\WebProfilerBundle\WebProfilerBundle;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Console\Command\Command;
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

        $consoleEnabled = \class_exists(Command::class);
        $domainEnabled = \interface_exists(RepositoryInterface::class) && ($config['domain']['enabled'] ?? true);
        $messengerEnabled = \interface_exists(MessageBusInterface::class);
        $eventStoreEnabled = $domainEnabled && ($config['domain']['event_store'] ?? false);
        $lockServiceEnabled = $domainEnabled && ($config['domain']['lock_service'] ?? false);

        if ($domainEnabled) {
            $loader->load('domain.yaml');
            $this->processDomainIntegration($container);
        }
        if ($eventStoreEnabled) {
            $loader->load('event-store.yaml');
            if ($consoleEnabled) {
                $loader->load('event-store-console.yaml');
            }
        }
        if ($lockServiceEnabled) {
            $loader->load('lock.yaml');
        }
        if ($messengerEnabled) {
            $loader->load('messenger.yaml');
        }
        if ($messengerEnabled && $domainEnabled) {
            $loader->load('event.yaml');
            if ($consoleEnabled) {
                $loader->load('event-console.yaml');
            }
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

                default: // Configuration should have handled invalid values
                    throw new \InvalidArgumentException();
            }
        }

        if ($runnerDefinition) {

            if ($config['runner']['metadata_cache']) {
                switch ($config['runner']['metadata_cache']) {

                    case 'array': // Do nothing, it's the default.
                        break;

                    case 'apcu':
                        // @todo raise error if APCu is not present or disabled.
                        $metadataCacheDefinition = (new Definition())
                            ->setClass(ApcuResultMetadataCache::class)
                            ->setArguments([(string)$config['runner']['metadata_cache_prefix']])
                            ->setPublic(false)
                        ;
                        $container->setDefinition('goat.result_metadata_cache', $metadataCacheDefinition);
                        $runnerDefinition->addMethodCall('setResultMetadataCache', [new Reference('goat.result_metadata_cache')]);
                        break;

                    default: // Configuration should have handled invalid values
                        throw new \InvalidArgumentException();
                }
            }

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
            $container->setAlias(Runner::class, 'goat.runner.default')->setPublic(true);
            $container->setAlias(QueryBuilder::class, 'goat.query_builder.default')->setPublic(true);
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
