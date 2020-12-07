<?php

declare(strict_types=1);

namespace Goat\Bridge\Symfony\Tests\DependencyInjection;

use Goat\Bridge\Symfony\DependencyInjection\GoatExtension;
use Goat\Dispatcher\Dispatcher;
use Goat\EventStore\EventStore;
use Goat\Lock\LockManager;
use Goat\MessageBroker\MessageBroker;
use Goat\Normalization\Serializer;
use Goat\Query\Symfony\GoatQueryBundle;
use Goat\Runner\Runner;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\MonologBundle\MonologBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\Serializer\Serializer as SymfonySerializer;

final class KernelConfigurationTest extends TestCase
{
    private function getContainer()
    {
        // Code inspired by the SncRedisBundle, all credits to its authors.
        $container = new ContainerBuilder(new ParameterBag([
            'kernel.debug'=> false,
            'kernel.bundles' => [
                GoatQueryBundle::class => ['all' => true],
                MonologBundle::class => ['all' => true],
            ],
            'kernel.cache_dir' => \sys_get_temp_dir(),
            'kernel.environment' => 'test',
            'kernel.root_dir' => \dirname(__DIR__),
        ]));

        // OK, we will need this.
        $runnerDefinition = new Definition();
        $runnerDefinition->setClass(Runner::class);
        $runnerDefinition->setSynthetic(true);
        $container->setDefinition('goat.runner.default', $runnerDefinition);
        $container->setAlias(Runner::class, 'goat.runner.default');

        // And this.
        $serializerDefinition = new Definition();
        $serializerDefinition->setClass(SymfonySerializer::class);
        $serializerDefinition->setSynthetic(true);
        $container->setDefinition('serializer', $serializerDefinition);
        $container->setAlias(SymfonySerializer::class, 'serializer');

        return $container;
    }

    private function getMinimalConfig(): array
    {
        return [
            'dispatcher' => [
                'enabled' => true,
                'with_event_store' => true,
                'with_lock' => true,
                'with_profiling' => true,
            ],
            'event_store' => [
                'enabled' => true,
            ],
            'lock' => [
                'enabled' => true,
            ],
            'normalization' => [
                'map' => [
                    'my_app.normalized_name' => \DateTimeImmutable::class,
                    'my_app.other_normalized_name' => \DateTime::class,
                ],
            ],
            'preferences' => [
                'enabled' => true,
                'schema' => [
                    'app_domain_some_variable' => [
                        'label' => "Some variable",
                        'description' => "Uncheck this value to deactive this feature",
                        'type' => 'bool',
                        'collection' => false,
                        'default' => true,
                    ],
                ],
            ],
        ];
    }

    /**
     * Test default config for resulting tagged services
     */
    public function testTaggedServicesConfigLoad()
    {
        $extension = new GoatExtension();
        $config = $this->getMinimalConfig();
        $extension->load([$config], $container = $this->getContainer());

        // Ensure event store configuration.
        self::assertTrue($container->hasAlias(EventStore::class));
        self::assertTrue($container->hasDefinition('goat.event_store'));

        // Ensure dispatcher configuration.
        self::assertTrue($container->hasAlias(Dispatcher::class));
        self::assertTrue($container->hasDefinition('goat.dispatcher'));

        // Ensure lock configuration.
        self::assertTrue($container->hasAlias(LockManager::class));
        self::assertTrue($container->hasDefinition('goat.lock'));

        // And message broker.
        self::assertTrue($container->hasAlias(MessageBroker::class));
        self::assertTrue($container->hasDefinition('goat.message_broker.default'));

        // And custom serializer.
        self::assertTrue($container->hasAlias(Serializer::class));
        self::assertTrue($container->hasDefinition('goat.serializer'));

        $container->compile();
    }
}
