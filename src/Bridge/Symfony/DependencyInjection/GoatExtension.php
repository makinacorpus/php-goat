<?php

declare(strict_types=1);

namespace Goat\Bridge\Symfony\DependencyInjection;

use Goat\Domain\Repository\RepositoryInterface;
use Goat\Preferences\Domain\Repository\ArrayPreferencesSchema;
use Goat\Preferences\Domain\Repository\PreferencesRepository;
use Goat\Preferences\Domain\Repository\PreferencesSchema;
use Monolog\Formatter\LineFormatter;
use Monolog\Processor\ProcessIdProcessor;
use Symfony\Bundle\MonologBundle\MonologBundle;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
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

        $consoleEnabled = \class_exists(Command::class);
        $domainEnabled = \interface_exists(RepositoryInterface::class) && ($config['domain']['enabled'] ?? true);
        $preferenceEnabled = \interface_exists(PreferencesRepository::class) && ($config['preferences']['enabled'] ?? false);
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
            $loader->load('projector.yaml');
            if ($consoleEnabled) {
                $loader->load('event-console.yaml');
                $loader->load('projector-console.yaml');
            }
        }

        if ($preferenceEnabled) {
            $loader->load('preferences.yaml');
            $this->processPreferences($container, $config['preferences'] ?? []);
            if ($messengerEnabled) {
                $loader->load('preferences-messenger.yaml');
            }
        }

        if (\in_array(MonologBundle::class, $container->getParameter('kernel.bundles'))) {
            $this->configureMonolog($container, $config['monolog'] ?? []);
        }
    }

    /**
     * Add a few bits of extra monolog configuration
     */
    private function configureMonolog(ContainerBuilder $container, array $config): void
    {
        $formatterDefinition = new Definition();
        $formatterDefinition->setClass(LineFormatter::class);

        if ($config['always_log_stacktrace'] ?? false) {
            $formatterDefinition->addMethodCall('includeStacktraces');
        }

        if ($config['log_pid'] ?? null) {
            $processorDefinition = new Definition();
            $processorDefinition->setClass(ProcessIdProcessor::class);
            $processorDefinition->addTag('monolog.processor');
            // This is the default, we only added "(%extra.process_id%)".
            $formatterDefinition->setArgument(0, \str_replace(
                "%", "%%",
                "[%datetime%] (%extra.process_id%) %channel%.%level_name%: %message% %context% %extra%\n"
            ));

            $container->setDefinition('goat.monolog.processor.pid', $processorDefinition);
        }

        // The magic here is we are going to just replace the default monolog
        // bundle service for everyone, sorry, but that was the easiest way
        // not involving user to modify its monolog.yaml file.
        // @see \Goat\Bridge\Symfony\DependencyInjection\Compiler\MonologConfigurationPass
        $container->setDefinition('goat.monolog.formatter.line', $formatterDefinition);
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
