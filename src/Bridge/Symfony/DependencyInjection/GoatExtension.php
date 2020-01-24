<?php

declare(strict_types=1);

namespace Goat\Bridge\Symfony\DependencyInjection;

use Doctrine\DBAL\Connection;
use Goat\Domain\Repository\RepositoryInterface;
use Goat\Driver\Configuration;
use Goat\Driver\ExtPgSQLDriver;
use Goat\Preferences\Domain\Repository\ArrayPreferencesSchema;
use Goat\Preferences\Domain\Repository\PreferencesRepository;
use Goat\Preferences\Domain\Repository\PreferencesSchema;
use Goat\Query\QueryBuilder;
use Goat\Runner\Runner;
use Goat\Runner\Metadata\ApcuResultMetadataCache;
use Symfony\Bundle\WebProfilerBundle\WebProfilerBundle;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
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
        $preferenceEnabled = \interface_exists(PreferencesRepository::class) && ($config['preferences']['enabled'] ?? false);
        $messengerEnabled = \interface_exists(MessageBusInterface::class);
        $eventStoreEnabled = $domainEnabled && ($config['domain']['event_store'] ?? false);
        $lockServiceEnabled = $domainEnabled && ($config['domain']['lock_service'] ?? false);

        $this->registerRunnerList($container, $config['runner'] ?? []);

        $loader->load('hydrator.yaml'); // Backward compatibility.

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
        $loader->load('normalization.yaml');
        $this->processNormalization($container, $config['normalization']['map'] ?? [], $config['normalization']['aliases'] ?? []);
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

        if ($preferenceEnabled) {
            $loader->load('preferences.yaml');
            $this->processPreferences($container, $config['preferences'] ?? []);
            if ($messengerEnabled) {
                $loader->load('preferences-messenger.yaml');
            }
        }

        if (\in_array(WebProfilerBundle::class, $container->getParameter('kernel.bundles'))) {
            $loader->load('profiler.yml');
        }
    }

    /**
     * Create runner list
     */
    private function registerRunnerList(ContainerBuilder $container, array $config): void
    {
        if (empty($config)) {
            // If configuration is empty, attempt automatic registration
            // of the 'default' connection using the 'doctrine' driver.
            $runnerDefinition = $this->createDoctrineRunner($container, 'default', $config);
            $this->configureRunner($container, 'default', $config, $runnerDefinition);
            return;
        }

        foreach ($config as $name => $runnerConfig) {
            $this->registerRunner($container, $name, $runnerConfig);
        }
    }

    /**
     * Validate and create a single runner
     */
    private function registerRunner(ContainerBuilder $container, string $name, array $config): void
    {
        $runnerDefinition = null;
        $runnerDriver = $config['driver'] ?? 'doctrine';

        switch ($runnerDriver) {

            case 'doctrine':
                $runnerDefinition = $this->createDoctrineRunner($container, $name, $config);
                break;

            case 'ext-pgsql':
                $runnerDefinition = $this->createExtPgSqlRunner($container, $name, $config);
                break;

            default: // Configuration should have handled invalid values
                throw new InvalidArgumentException(\sprintf(
                    "Could not create the goat.runner.%s runner service: driver '%s' is unsupported",
                    $name, $runnerDriver
                ));
        }

        $this->configureRunner($container, $name, $config, $runnerDefinition);
    }

    /**
     * Configure single runner
     */
    private function configureRunner(ContainerBuilder $container, string $name, array $config, Definition $runnerDefinition): void
    {
        // Metadata cache configuration
        if ($config['metadata_cache']) {
            switch ($config['metadata_cache']) {

                case 'array': // Do nothing, it's the default.
                    break;

                case 'apcu':
                    if (isset($config['metadata_cache_prefix'])) {
                        $cachePrefix = (string)$config['metadata_cache_prefix'];
                    } else {
                        $cachePrefix = 'goat_metadata_cache.'.$name;
                    }
                    // @todo raise error if APCu is not present or disabled.
                    $metadataCacheDefinition = (new Definition())
                        ->setClass(ApcuResultMetadataCache::class)
                        ->setArguments([$cachePrefix])
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
            ->setFactory([new Reference('goat.runner.'.$name), 'getQueryBuilder'])
            ->addTag('container.hot_path')
        ;

        $container->setDefinition('goat.query_builder.'.$name, $queryBuilderDefinition);
        if ('default' === $name) {
            $container->setAlias(Runner::class, 'goat.runner.default')->setPublic(true);
        }
    }

    /**
     * Create a single doctrine runner
     */
    private function createDoctrineRunner(ContainerBuilder $container, string $name, array $config): Definition
    {
        /*
         * @todo
         *   Find a smart way to do this, actually we cannot because when
         *   configuring the extension, we are isolated from the rest, and
         *   I'm too lazy to write a compilation pass right now.
         *
        if (!$container->hasDefinition($doctrineConnectionServiceId) && !$container->hasAlias($doctrineConnectionServiceId)) {
            throw new InvalidArgumentException(\sprintf(
                "Could not create the goat.runner.%s runner service: could not find %s doctrine/dbal connection service",
                $name, $doctrineConnectionServiceId
            ));
        }
         */

        $runnerId = "goat.runner.".$name;

        $doctrineConnectionServiceId = Connection::class;
        if (isset($config['doctrine_connection'])) {
            $doctrineConnectionServiceId = 'doctrine.dbal.'.$config['doctrine_connection'].'_connection';
        }

        $runnerDefinition = (new Definition())
            ->setClass(Runner::class)
            ->setPublic(true)
            ->setFactory([RunnerFactory::class, 'createFromDoctrineConnection'])
            // @todo should the converter be configurable as well?
            ->setArguments([new Reference($doctrineConnectionServiceId), new Reference('goat.converter.default')])
            ->addTag('container.hot_path')
        ;

        $container->setDefinition($runnerId, $runnerDefinition);

        return $runnerDefinition;
    }

    /**
     * Create a single ext-pgsql runner
     */
    private function createExtPgSqlRunner(ContainerBuilder $container, string $name, array $config): Definition
    {
        $configurationId = 'goat.runner.'.$name.'.conf';
        $driverId = 'goat.runner.'.$name.'.driver';
        $runnerId = 'goat.runner.'.$name;

        $configurationDefinition = (new Definition())
            ->setClass(Configuration::class)
            ->setPublic(false)
            ->setFactory([Configuration::class, 'fromString'])
            ->setArguments([$config['url']])
            ->addTag('container.hot_path')
        ;

        $driverDefinition = (new Definition())
            ->setClass(ExtPgSQLDriver::class)
            ->setPublic(false)
            ->addMethodCall('setConfiguration', [new Reference($configurationId)])
            ->addTag('container.hot_path')
        ;

        $runnerDefinition = (new Definition())
            ->setClass(Runner::class)
            ->setPublic(true)
            ->setFactory([new Reference($driverId), 'getRunner'])
            ->addTag('container.hot_path')
        ;

        $container->addDefinitions([
            $configurationId => $configurationDefinition,
            $driverId => $driverDefinition,
            $runnerId => $runnerDefinition,
        ]);

        return $runnerDefinition;
    }

    /**
     * Normalize type name, do not check for type existence
     */
    private function normalizeType(string $type, string $key): string
    {
        if (!\is_string($type)) {
            throw new InvalidArgumentException(\sprintf(
                "goat.normalization.map: key '%s': value must be a string",
                $key
            ));
        }
        if (\ctype_digit($key)) {
            throw new InvalidArgumentException(\sprintf(
                "goat.normalization.map: key '%s': cannot be numeric",
                $key
            ));
        }
        // Normalize to FQDN
        return \ltrim(\trim($type), '\\');
    }

    /**
     * Process type normalization map and aliases.
     */
    private function processNormalization(ContainerBuilder $container, array $map, array $aliases): void
    {
        $types = [];
        foreach ($map as $key => $type) {
            $type = $this->normalizeType($type, $key);
            if ('string' !== $type && 'array' !== $type && 'null' !== $type && !\class_exists($type)) {
                throw new InvalidArgumentException(\sprintf(
                    "goat.normalization.map: key '%s': class '%s' does not exist",
                    $key, $type
                ));
            }
            if ($existing = ($types[$type] ?? null)) {
                throw new InvalidArgumentException(\sprintf(
                    "goat.normalization.map: key '%s': class '%s' previously defined at key '%s'",
                    $key, $type, $existing
                ));
            }
            // Value is normalized, fix incomming array.
            $map[$key] = $type;
            $types[$type] = $key;
        }

        foreach ($aliases as $alias => $type) {
            $type = $this->normalizeType($type, $key);
            // Alias toward another alias, or alias toward an PHP native type?
            if (!isset($map[$alias]) && !\in_array($type, $map)) {
                if ($existing = ($types[$type] ?? null)) {
                    throw new InvalidArgumentException(\sprintf(
                        "goat.normalization.alias: key '%s': normalized name or type '%s' is not defined in goat.normalization.map",
                        $alias, $type, $existing
                    ));
                }
            }
            $aliases[$alias] = $type;
        }

        $container->getDefinition('goat.domain.name_map')->setArguments([$map, $aliases]);
    }

    /**
     * Process preferences.
     */
    private function processPreferences(ContainerBuilder $container, array $config)
    {
        // @todo caching strategy.
        if (isset($config['schema'])) {
            $schemaDefinition = new Definition();
            $schemaDefinition->setClass(ArrayPreferencesSchema::class);
            // In theory, our configuration was correctly registered, this should
            // work gracefully.
            $schemaDefinition->setArguments([$config['schema']]);
            $container->setDefinition('goat.preferences.schema', $schemaDefinition);
            $container->setAlias(PreferencesSchema::class, 'goat.preferences.schema');
        }
    }

    /**
     * Integration with makinacorpus/goat-domain package.
     */
    private function processDomainIntegration(ContainerBuilder $container)
    {
        // @todo is there actually anything to do?
    }

    /**
     * {@inheritdoc}
     */
    public function getConfiguration(array $config, ContainerBuilder $container)
    {
        return new GoatConfiguration();
    }
}
