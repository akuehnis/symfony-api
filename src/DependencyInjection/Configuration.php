<?php
namespace Akuehnis\SymfonyApi\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder('akuehnis_symfony_api');

        $treeBuilder->getRootNode()
            ->children()
                ->arrayNode('areas')
                    ->info('Filter the routes that are documented')
                    ->useAttributeAsKey('name')
                    ->example(['default' => ['path_pattterns' => []]])
                    ->prototype('array')
                        ->addDefaultsIfNotSet()
                        ->children()
                            ->arrayNode('path_patterns')
                                ->defaultValue([])
                                ->example(['^/api', '^/api(?!/admin)'])
                                ->prototype('scalar')->end()
                            ->end()
                            ->arrayNode('documentation')
                                ->useAttributeAsKey('key')
                                ->defaultValue([])
                                ->info('The documentation used for area')
                                ->example(['info' => ['title' => 'My App']])
                                ->prototype('variable')->end()
                            ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
