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
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('goat');

        $rootNode
            ->children()
                ->arrayNode('runner')
                    ->children()
                        ->enumNode('driver')
                            ->values(['doctrine'])
                            ->defaultNull()
                        ->end()
                        ->enumNode('metadata_cache')
                            ->values(['array', 'apcu'])
                            ->defaultNull()
                        ->end()
                        ->scalarNode('metadata_cache_prefix')
                            ->defaultNull()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('query')
                    ->children()
                        ->booleanNode('enabled')->defaultTrue()->end()
                    ->end()
                ->end()
                ->arrayNode('domain')
                    ->children()
                        ->booleanNode('enabled')->defaultTrue()->end()
                        ->booleanNode('event_store')->defaultFalse()->end()
                        ->booleanNode('lock_service')->defaultFalse()->end()
                    ->end()
                ->end()
                ->arrayNode('normalization')
                    ->children()
                        ->variableNode('map')->end()
                        ->variableNode('aliases')->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
