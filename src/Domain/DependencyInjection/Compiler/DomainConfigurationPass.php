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

    /** @var string */
    private $dispatcherId;

    /** @var string */
    private $projectorTag;

    /** @var string */
    private $eventStoreId;

    /** @var string */
    private $lockServiceId;

    /** @var string */
    private $loggerId;

    /** @var string */
    private $messengerSerializerServiceId;

    /** @var string */
    private $transactionHandlerTag;

    /**
     * Default constructor
     */
    public function __construct(
        string $dispatcherId = 'goat.domain.dispatcher',
        string $projectorTag = 'goat.domain.dispatcher.projector',
        string $transactionHandlerTag = 'goat.domain.transaction_handler',
        string $eventStoreId = 'goat.domain.event_store',
        string $lockServiceId = 'goat.domain.lock_service',
        string $messengerSerializerServiceId = 'messenger.transport.symfony_serializer',
        string $loggerId = 'logger')
    {
        $this->dispatcherId = $dispatcherId;
        $this->projectorTag = $projectorTag;
        $this->eventStoreId = $eventStoreId;
        $this->lockServiceId = $lockServiceId;
        $this->loggerId = $loggerId;
        $this->messengerSerializerServiceId = $messengerSerializerServiceId;
        $this->transactionHandlerTag = $transactionHandlerTag;
    }

    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        $hasLogger = $container->has($this->loggerId);
        $hasDispatcher = $container->has($this->dispatcherId);
        $hasEventStore = $container->has($this->eventStoreId);
        $hasLockService = $container->has($this->lockServiceId);
        $isDebug = $container->getParameter('kernel.debug');

        if ($hasEventStore) {
            $eventStoreDef = $container->getDefinition($this->eventStoreId);
            if (\is_subclass_of($eventStoreDef->getClass(), AbstractEventStore::class)) {
                if ($container->has('serializer')) {
                    $eventStoreDef->addMethodCall('setSerializer', [new Reference('serializer')]);
                }
                if ($hasLogger) {
                    $eventStoreDef->addMethodCall('setLogger', [new Reference($this->loggerId)]);
                }
                if ($isDebug) {
                    $eventStoreDef->addMethodCall('setDebug', [true]);
                }
            }
        }

        if ($hasDispatcher) {
            $dispatcherDef = $container->getDefinition($this->dispatcherId);
            if ($references = $this->findAndSortTaggedServices($this->transactionHandlerTag, $container)) {
                $dispatcherDef->addMethodCall('setTransactionHandlers', [$references]);
            }
            if ($references = $this->findAndSortTaggedServices($this->projectorTag, $container)) {
                $dispatcherDef->addMethodCall('setProjectors', [$references]);
            }
            if ($hasEventStore) {
                $dispatcherDef->addMethodCall('setEventStore', [new Reference($this->eventStoreId)]);
            }
            if ($hasLockService) {
                $dispatcherDef->addMethodCall('setLockService', [new Reference($this->lockServiceId)]);
            }
            if ($hasLogger) {
                $dispatcherDef->addMethodCall('setLogger', [new Reference($this->loggerId)]);
            }
            if ($isDebug) {
                $dispatcherDef->addMethodCall('setDebug', [true]);
            }
        }

        if ($hasLockService) {
            $lockServiceDef = $container->getDefinition($this->lockServiceId);
            if ($hasLogger) {
                $lockServiceDef->addMethodCall('setLogger', [new Reference($this->loggerId)]);
            }
            if ($isDebug) {
                $lockServiceDef->addMethodCall('setDebug', [true]);
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
