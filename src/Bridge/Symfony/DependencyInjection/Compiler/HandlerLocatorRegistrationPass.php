<?php

declare(strict_types=1);

namespace Goat\Bridge\Symfony\DependencyInjection\Compiler;

use Goat\Dispatcher\Handler;
use Goat\Dispatcher\Cache\HandlerLocator\HandlerReferenceListPhpDumper;
use Goat\Dispatcher\HandlerLocator\NullHandlerReferenceList;
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
        $dumpedClassName = HandlerReferenceListPhpDumper::getDumpedClassName('command');
        $dumpedFileName = HandlerReferenceListPhpDumper::getFilename($container->getParameter('kernel.cache_dir'), 'command');

        $dumper = new HandlerReferenceListPhpDumper($dumpedFileName, false);

        foreach ($container->findTaggedServiceIds($this->handlerTag, true) as $id => $attributes) {
            $definition = $container->getDefinition($id);
            $className = $definition->getClass();

            if (!$reflexion = $container->getReflectionClass($className)) {
                throw new InvalidArgumentException(\sprintf('Class "%s" used for service "%s" cannot be found.', $className, $id));
            }

            if ($reflexion->implementsInterface(Handler::class)) {
                $definition->setPublic(true);
                $dumper->appendFromClass($className, $id);
            }
        }

        if ($dumper->isEmpty()) {
            $dumper->delete();

            $serviceClassName = NullHandlerReferenceList::class;
            $definition = new Definition();
            $definition->setClass($serviceClassName);
            $definition->setPublic(false);
            $container->setDefinition($serviceClassName, $definition);
        } else {
            $dumper->dump($dumpedClassName);

            $serviceClassName = HandlerReferenceListPhpDumper::getDumpedClassNamespace() . '\\' . $dumpedClassName;
            $definition = new Definition();
            $definition->setClass($serviceClassName);
            $definition->setFile($dumpedFileName);
            $definition->setPublic(false);
            $container->setDefinition($serviceClassName, $definition);
        }

        $container
            ->getDefinition('goat.dispatcher.handler_locator.default')
            ->setArguments([new Reference($serviceClassName)])
            ->addMethodCall('setContainer', [new Reference('service_container')])
        ;
    }
}
