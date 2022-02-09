<?php

declare(strict_types=1);

namespace Goat\Bridge\Symfony\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class GoatConfiguration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder('goat');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->arrayNode('monolog')
                    ->children()
                        ->booleanNode('log_pid')->defaultTrue()->end()
                        ->booleanNode('always_log_stacktrace')->defaultFalse()->end()
                    ->end()
                ->end()
                ->arrayNode('dispatcher')
                    ->children()
                        ->booleanNode('enabled')->defaultFalse()->end()
                        ->booleanNode('with_logging')->defaultTrue()->end()
                        ->booleanNode('with_lock')->defaultFalse()->end()
                        ->booleanNode('with_event_store')->defaultFalse()->end()
                        ->booleanNode('with_profiling')->defaultTrue()->end()
                        ->booleanNode('with_retry')->defaultTrue()->end()
                        ->booleanNode('with_transaction')->defaultTrue()->end()
                    ->end()
                ->end()
                ->arrayNode('lock')
                    ->children()
                        ->booleanNode('enabled')->defaultFalse()->end()
                    ->end()
                ->end()
                ->arrayNode('message_broker')
                    ->children()
                        ->booleanNode('enabled')->defaultTrue()->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
