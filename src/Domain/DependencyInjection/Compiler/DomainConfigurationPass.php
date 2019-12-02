<?php

declare(strict_types=1);

namespace Goat\Domain\DependencyInjection\Compiler;

use Goat\Domain\EventStore\AbstractEventStore;
use Goat\Domain\Messenger\NameMapMessengerSerializer;
use Goat\Domain\Serializer\NameMapSerializer;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PriorityTaggedServiceTrait;

final class DomainConfigurationPass implements CompilerPassInterface
{
    use PriorityTaggedServiceTrait;

    private $dispatcherId;
    private $eventStoreId;
    private $lockServiceId;
    private $messengerSerializerServiceId;
    private $transactionHandlerTag;

    /**
     * Default constructor
     */
    public function __construct(
        string $dispatcherId = 'goat.domain.dispatcher',
        string $transactionHandlerTag = 'goat.domain.transaction_handler',
        string $eventStoreId = 'goat.domain.event_store',
        string $lockServiceId = 'goat.domain.lock_service',
        string $messengerSerializerServiceId = 'messenger.transport.symfony_serializer')
    {
        $this->dispatcherId = $dispatcherId;
        $this->eventStoreId = $eventStoreId;
        $this->lockServiceId = $lockServiceId;
        $this->messengerSerializerServiceId = $messengerSerializerServiceId;
        $this->transactionHandlerTag = $transactionHandlerTag;
    }

    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        $hasDispatcher = $container->has($this->dispatcherId);
        $hasEventStore = $container->has($this->eventStoreId);
        $hasLockService = $container->has($this->lockServiceId);

        if ($hasEventStore) {
            $eventStoreDef = $container->getDefinition($this->eventStoreId);
            if (\is_subclass_of($eventStoreDef->getClass(), AbstractEventStore::class)) {
                if ($container->has('serializer')) {
                    $eventStoreDef->addMethodCall('setSerializer', [new Reference('serializer')]);
                }
            }
        }

        if ($hasDispatcher) {
            $dispatcherDef = $container->getDefinition($this->dispatcherId);
            if ($references = $this->findAndSortTaggedServices($this->transactionHandlerTag, $container)) {
                $dispatcherDef->addMethodCall('setTransactionHandlers', [$references]);
            }
            if ($hasEventStore) {
                $dispatcherDef->addMethodCall('setEventStore', [new Reference($this->eventStoreId)]);
            }
            if ($hasLockService) {
                $dispatcherDef->addMethodCall('setLockService', [new Reference($this->lockServiceId)]);
            }
        }

        $serializerServiceId = 'serializer';
        if ($container->hasDefinition($serializerServiceId)) {
            $decoratorInnerId = $serializerServiceId.'.inner';
            $definition = new Definition();
            $definition->setClass(NameMapSerializer::class);
            $definition->setDecoratedService($serializerServiceId, $decoratorInnerId);
            $definition->setArguments([new Reference('goat.domain.name_map'), new Reference($decoratorInnerId)]);
            $container->setDefinition('goat.domain.name_map.serializer', $definition);
        }

        if ($container->hasDefinition($this->messengerSerializerServiceId)) {
            $decoratorInnerId = $this->messengerSerializerServiceId.'.inner';
            $definition = new Definition();
            $definition->setClass(NameMapMessengerSerializer::class);
            $definition->setDecoratedService($this->messengerSerializerServiceId, $decoratorInnerId);
            $definition->setArguments([new Reference('goat.domain.name_map'), new Reference($decoratorInnerId)]);
            $container->setDefinition('goat.domain.name_map.messenger_serializer', $definition);
        }
    }
}
