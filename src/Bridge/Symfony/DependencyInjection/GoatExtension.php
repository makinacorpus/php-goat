<?php

declare(strict_types=1);

namespace Goat\Bridge\Symfony\DependencyInjection;

use Goat\Dispatcher\Decorator\EventStoreDispatcherDecorator;
use Goat\Dispatcher\Decorator\LoggingDispatcherDecorator;
use Goat\Dispatcher\Decorator\ParallelExecutionBlockerDispatcherDecorator;
use Goat\Dispatcher\Decorator\ProfilingDispatcherDecorator;
use Goat\Dispatcher\Decorator\RetryDispatcherDecorator;
use Goat\Dispatcher\Decorator\TransactionDispatcherDecorator;
use Monolog\Formatter\LineFormatter;
use Monolog\Processor\ProcessIdProcessor;
use Symfony\Bundle\MonologBundle\MonologBundle;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Twig\Environment;

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

        $dispatcherEnabled = $config['dispatcher']['enabled'] ?? false;
        $eventStoreEnabled = $config['event_store']['enabled'] ?? false;
        $lockEnabled = $config['lock']['enabled'] ?? false;
        $messageBrokerEnabled = $config['message_broker']['enabled'] ?? false;
        $twigEnabled = \class_exists(Environment::class);

        $loader->load('normalization.yaml');
        $this->processNormalization($container, $config['normalization'] ?? []);

        if ($messageBrokerEnabled) {
            $loader->load('message-broker.yaml');
        }

        if ($eventStoreEnabled) {
            $loader->load('event-store.yaml');
            $loader->load('event-projector.yaml');
        }

        if ($eventStoreEnabled && $consoleEnabled) {
            $loader->load('event-store-console.yaml');
            $loader->load('event-projector-console.yaml');
        }

        if ($lockEnabled) {
            $loader->load('lock.yaml');
        }

        if ($dispatcherEnabled) {
            $loader->load('dispatcher.yaml');
            $this->configureDispatcher($container, $config['dispatcher'] ?? []);
        }

        if ($dispatcherEnabled && $consoleEnabled) {
            $loader->load('dispatcher-console.yaml');
        }

        if ($twigEnabled) {
            $loader->load('twig.yaml');
        }

        if (\in_array(MonologBundle::class, $container->getParameter('kernel.bundles'))) {
            $this->configureMonolog($container, $config['monolog'] ?? []);
        }
    }

    private function configureDispatcher(ContainerBuilder $container, array $config): void
    {
        if ($config['with_logging']) {
            $decoratedInnerId = 'goat.dispatcher.logging.inner';
            $decoratorDef = new Definition();
            $decoratorDef->setClass(LoggingDispatcherDecorator::class);
            $decoratorDef->setDecoratedService('goat.dispatcher', $decoratedInnerId, 1000);
            $decoratorDef->setArguments([
                new Reference($decoratedInnerId),
            ]);
            $container->setDefinition('goat.dispatcher.decorator.logging', $decoratorDef);
        }

        if ($config['with_lock']) {
            if (!$container->hasDefinition('goat.lock') && !$container->hasAlias('goat.lock')) {
                throw new InvalidArgumentException("You must set goat.lock.enabled to true in order to be able to enable goat.dispatcher.with_lock");
            }

            $decoratedInnerId = 'goat.dispatcher.lock.inner';
            $decoratorDef = new Definition();
            $decoratorDef->setClass(ParallelExecutionBlockerDispatcherDecorator::class);
            $decoratorDef->setDecoratedService('goat.dispatcher', $decoratedInnerId, 800);
            $decoratorDef->setArguments([
                new Reference($decoratedInnerId),
                new Reference('goat.lock'),
            ]);
            $container->setDefinition('goat.dispatcher.decorator.lock', $decoratorDef);
        }

        if ($config['with_event_store']) {
            if (!$container->hasDefinition('goat.event_store') && !$container->hasAlias('goat.event_store')) {
                throw new InvalidArgumentException("You must set goat.event_store.enabled to true in order to be able to enable goat.dispatcher.with_event_store");
            }

            $decoratedInnerId = 'goat.dispatcher.event_store.inner';
            $decoratorDef = new Definition();
            $decoratorDef->setClass(EventStoreDispatcherDecorator::class);
            $decoratorDef->setDecoratedService('goat.dispatcher', $decoratedInnerId, 600);
            $decoratorDef->setArguments([
                new Reference($decoratedInnerId),
                new Reference('goat.event_store'),
            ]);
            $container->setDefinition('goat.dispatcher.decorator.event_store', $decoratorDef);
        }

        if ($config['with_profiling']) {
            $decoratedInnerId = 'goat.dispatcher.profiling.inner';
            $decoratorDef = new Definition();
            $decoratorDef->setClass(ProfilingDispatcherDecorator::class);
            $decoratorDef->setDecoratedService('goat.dispatcher', $decoratedInnerId, 400);
            $decoratorDef->setArguments([
                new Reference($decoratedInnerId),
            ]);
            $container->setDefinition('goat.dispatcher.decorator.profiling', $decoratorDef);
        }

        if ($config['with_retry']) {
            if (!$container->hasDefinition('goat.message_broker') && !$container->hasAlias('goat.message_broker')) {
                throw new InvalidArgumentException("You must set goat.message_broker.enabled to true in order to be able to enable goat.dispatcher.with_retry");
            }

            $decoratedInnerId = 'goat.dispatcher.retry.inner';
            $decoratorDef = new Definition();
            $decoratorDef->setClass(RetryDispatcherDecorator::class);
            $decoratorDef->setDecoratedService('goat.dispatcher', $decoratedInnerId, 400);
            $decoratorDef->setArguments([
                new Reference($decoratedInnerId),
                new Reference('goat.dispatcher.retry_strategy'),
                new Reference('goat.message_broker')
            ]);
            $container->setDefinition('goat.dispatcher.decorator.retry', $decoratorDef);
        }

        if ($config['with_transaction']) {
            $decoratedInnerId = 'goat.dispatcher.transaction.inner';
            $decoratorDef = new Definition();
            $decoratorDef->setClass(TransactionDispatcherDecorator::class);
            $decoratorDef->setDecoratedService('goat.dispatcher', $decoratedInnerId, 200);
            $decoratorDef->setArguments([
                new Reference($decoratedInnerId),
                [] // @todo
            ]);
            $container->setDefinition('goat.dispatcher.decorator.transaction', $decoratorDef);
        }
    }

    /**
     * Add a few bits of extra monolog configuration.?
     *
     * @codeCoverageIgnore
     * @todo Test this.
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
     * Normalize type name, do not check for type existence?
     *
     * @codeCoverageIgnore
     * @todo Export this into a testable class.
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
    private function processNormalization(ContainerBuilder $container, array $config): void
    {
        $container->getDefinition('goat.name_map.strategy.prefix')->setArguments([
            $config['default_strategy']['app_name'] ?? 'App',
            $config['default_strategy']['class_prefix'] ?? 'App',
        ]);

        foreach (($config['strategy'] ?? []) as $context => $serviceId) {
            $container->getDefinition('goat.name_map')->addMethodCall('setNameMappingStrategryFor', [$context, new Reference($serviceId)]);
        }
        foreach (($config['static'] ?? []) as $context => $data) {
            $this->processNormalizationStaticForContext($container, $context, $data['map'] ?? [], $data['aliases'] ?? []);
        }
    }

    /**
     * Process type normalization map and aliases.
     */
    private function processNormalizationStaticForContext(ContainerBuilder $container, string $context, array $map, array $aliases): void
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

        $container->getDefinition('goat.name_map')->addMethodCall('setStaticNameMapFor', [$context, $map, $aliases]);
    }

    /**
     * {@inheritdoc}
     */
    public function getConfiguration(array $config, ContainerBuilder $container)
    {
        return new GoatConfiguration();
    }
}
