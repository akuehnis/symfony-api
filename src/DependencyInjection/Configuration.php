<?php
namespace Akuehnis\SymfonyApi\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder('symfony-api');

        $treeBuilder->getRootNode()
            ->children()
                ->arrayNode('documentation')
                    ->useAttributeAsKey('key')
                    ->info('The documentation used as base')
                    ->example(['info' => ['title' => 'My App']])
                    ->prototype('variable')->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
