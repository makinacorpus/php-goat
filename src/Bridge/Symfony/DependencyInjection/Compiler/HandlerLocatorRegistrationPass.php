<?php

declare(strict_types=1);

namespace Goat\Bridge\Symfony\DependencyInjection\Compiler;

use Goat\Dispatcher\Handler;
use Goat\Dispatcher\HandlerLocator\HandlerReferenceList;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;

final class HandlerLocatorRegistrationPass implements CompilerPassInterface
{
    private string $handlerTag;

    public function __construct(string $handlerTag = 'goat.message_handler')
    {
        $this->handlerTag = $handlerTag;
    }

    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        $referenceList = HandlerReferenceList::create();

        foreach ($container->findTaggedServiceIds($this->handlerTag, true) as $id => $attributes) {
            $definition = $container->getDefinition($id);
            $className = $definition->getClass();

            if (!$reflexion = $container->getReflectionClass($className)) {
                throw new InvalidArgumentException(\sprintf('Class "%s" used for service "%s" cannot be found.', $className, $id));
            }
            if ($reflexion->implementsInterface(Handler::class)) {
                // @todo Find another way, for later.
                $definition->setPublic(true);
                $referenceList->appendFromClass($className, $id);
            }
        }

        $referenceListServiceId = 'goat.dispatcher.handler_locator.default.reference_list';
        $referenceListDefinition = new Definition();
        $referenceListDefinition->setClass(HandlerReferenceList::class);
        $referenceListDefinition->setFactory([HandlerReferenceList::class, 'fromArray']);
        $referenceListDefinition->setPrivate(true);
        $referenceListDefinition->setArguments([$referenceList->toArray()]);
        $container->setDefinition($referenceListServiceId, $referenceListDefinition);

        $container
            ->getDefinition('goat.dispatcher.handler_locator.default')
            ->setArguments([new Reference($referenceListServiceId)])
            ->addMethodCall('setContainer', [new Reference('service_container')])
        ;
    }
}
