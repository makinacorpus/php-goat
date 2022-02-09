<?php

declare(strict_types=1);

namespace Goat\Bridge\Symfony\Tests\DependencyInjection;

use Goat\Bridge\Symfony\DependencyInjection\GoatExtension;
use Goat\Dispatcher\Dispatcher;
use Goat\Lock\LockManager;
use Goat\MessageBroker\MessageBroker;
use Goat\Query\Symfony\GoatQueryBundle;
use Goat\Runner\Runner;
use MakinaCorpus\EventStore\EventStore;
use MakinaCorpus\Normalization\NameMap;
use MakinaCorpus\Normalization\Serializer;
use MakinaCorpus\Normalization\NameMap\DefaultNameMap;
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

        // And this.
        $eventStoreDefinition = new Definition();
        $eventStoreDefinition->setClass(EventStore::class);
        $eventStoreDefinition->setSynthetic(true);
        $container->setDefinition('event_store.event_store', $eventStoreDefinition);
        $container->setAlias(EventStore::class, 'event_store.event_store');

        // Strategy definition
        $stupidStrategyDefinition = new Definition();
        $stupidStrategyDefinition->setClass(StupidNameMappingStrategy::class);
        $container->setDefinition(StupidNameMappingStrategy::class, $stupidStrategyDefinition);

        $nameMapDefinition = new Definition();
        $nameMapDefinition->setClass(DefaultNameMap::class);
        $container->setDefinition(NameMap::class, $nameMapDefinition);
        $container->setAlias('normalization.name_map', NameMap::class);

        $normalizationSerializerDefinition = new Definition();
        $normalizationSerializerDefinition->setClass(Serializer::class);
        $container->setDefinition(Serializer::class, $normalizationSerializerDefinition);
        $container->setAlias('normalization.serializer', Serializer::class);

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
            'lock' => [
                'enabled' => true,
            ],
            'message_broker' => [
                'enabled' => true,
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

        // Ensure dispatcher configuration.
        self::assertTrue($container->hasAlias(Dispatcher::class));
        self::assertTrue($container->hasDefinition('goat.dispatcher'));

        // Ensure lock configuration.
        self::assertTrue($container->hasAlias(LockManager::class));
        self::assertTrue($container->hasDefinition('goat.lock'));

        // And message broker.
        self::assertTrue($container->hasAlias(MessageBroker::class));
        self::assertTrue($container->hasDefinition('goat.message_broker.default'));

        $container->compile();
    }
}
