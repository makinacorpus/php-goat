<?php

declare(strict_types=1);

namespace Goat\Bridge\Symfony\Tests\DependencyInjection;

use Goat\Bridge\Symfony\DependencyInjection\GoatExtension;
use Goat\Domain\Event\Dispatcher;
use Goat\Domain\EventStore\EventStore;
use Goat\Domain\MessageBroker\MessageBroker;
use Goat\Query\Symfony\GoatQueryBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\MonologBundle\MonologBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

final class KernelConfigurationTest extends TestCase
{
    private function getContainer()
    {
        // Code inspired by the SncRedisBundle, all credits to its authors.
        return new ContainerBuilder(new ParameterBag([
            'kernel.debug'=> false,
            'kernel.bundles' => [
                GoatQueryBundle::class => ['all' => true],
                MonologBundle::class => ['all' => true],
            ],
            'kernel.cache_dir' => \sys_get_temp_dir(),
            'kernel.environment' => 'test',
            'kernel.root_dir' => \dirname(__DIR__),
        ]));
    }

    private function getMinimalConfig(): array
    {
        return [
            'domain' => [
                'enabled' => true,
                'event_store' => true,
                'lock_service' => true,
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

        // Ensure event store configuration
        self::assertTrue($container->hasAlias(EventStore::class));
        self::assertTrue($container->hasDefinition('goat.domain.event_store'));

        // Ensure dispatcher configuration
        self::assertTrue($container->hasAlias(Dispatcher::class));
        self::assertTrue($container->hasDefinition('goat.domain.dispatcher'));

        // And message broker configuration
        self::assertTrue($container->hasAlias(MessageBroker::class));
        self::assertTrue($container->hasDefinition('goat.domain.message_broker.default'));
    }
}
