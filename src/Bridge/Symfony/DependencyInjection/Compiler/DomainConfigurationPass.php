<?php

declare(strict_types=1);

namespace Goat\Bridge\Symfony\DependencyInjection\Compiler;

use Goat\Bridge\Symfony\Messenger\Serializer\NameMapMessengerSerializer;
use Goat\Bridge\Symfony\Serializer\NameMapSerializer;
use Goat\EventStore\AbstractEventStore;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PriorityTaggedServiceTrait;

final class DomainConfigurationPass implements CompilerPassInterface
{
    use PriorityTaggedServiceTrait;

    private string $projectorTag;
    private string $projectorRegistryId;
    private string $eventStoreId;
    private string $messengerSerializerServiceId;
    private string $transactionHandlerTag;

    /**
     * Default constructor
     */
    public function __construct(
        string $projectorTag = 'goat.projector',
        string $projectorRegistryId = 'goat.projector.registry',
        string $transactionHandlerTag = 'goat.transaction_handler',
        string $eventStoreId = 'goat.event_store',
        string $lockServiceId = 'goat.lock',
        string $messengerSerializerServiceId = 'messenger.transport.symfony_serializer'
    ) {
        $this->projectorTag = $projectorTag;
        $this->projectorRegistryId = $projectorRegistryId;
        $this->eventStoreId = $eventStoreId;
        $this->messengerSerializerServiceId = $messengerSerializerServiceId;
        $this->transactionHandlerTag = $transactionHandlerTag;
    }

    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        $hasEventStore = $container->has($this->eventStoreId);
        $hasProjectorRegistry =  $container->has($this->projectorRegistryId);

        if ($hasEventStore) {
            $eventStoreDef = $container->getDefinition($this->eventStoreId);
            if (\is_subclass_of($eventStoreDef->getClass(), AbstractEventStore::class)) {
                if ($container->has('serializer')) {
                    $eventStoreDef->addMethodCall('setSerializer', [new Reference('serializer')]);
                }
            }
        }

        if ($hasProjectorRegistry) {
            $projectorRegistryDef = $container->getDefinition($this->projectorRegistryId);
            if ($references = $this->findAndSortTaggedServices($this->projectorTag, $container)) {
                $projectorRegistryDef->addMethodCall('setProjectors', [$references]);
            } else {
                // Avoid it to crash.
                $projectorRegistryDef->addMethodCall('setProjectors', [[]]);
            }
        }

        $serializerServiceId = 'serializer';
        if ($container->hasDefinition($serializerServiceId)) {
            $decoratorInnerId = $serializerServiceId.'.inner';
            $definition = new Definition();
            $definition->setClass(NameMapSerializer::class);
            $definition->setDecoratedService($serializerServiceId, $decoratorInnerId);
            $definition->setArguments([new Reference('goat.name_map'), new Reference($decoratorInnerId)]);
            $container->setDefinition('goat.name_map.serializer', $definition);
        }

        if ($container->hasDefinition($this->messengerSerializerServiceId)) {
            $decoratorInnerId = $this->messengerSerializerServiceId.'.inner';
            $definition = new Definition();
            $definition->setClass(NameMapMessengerSerializer::class);
            $definition->setDecoratedService($this->messengerSerializerServiceId, $decoratorInnerId);
            $definition->setArguments([new Reference('goat.name_map'), new Reference($decoratorInnerId)]);
            $container->setDefinition('goat.name_map.messenger_serializer', $definition);
        }
    }

    private function containerIsSubtypeOf(ContainerBuilder $container, Definition $definition, string $parentClassOrInterface): bool
    {
        $class = $container->getParameterBag()->resolveValue($definition->getClass());
        $refClass = $container->getReflectionClass($class);

        return $refClass->implementsInterface($parentClassOrInterface) || $refClass->isSubclassOf($parentClassOrInterface);
    }
}
