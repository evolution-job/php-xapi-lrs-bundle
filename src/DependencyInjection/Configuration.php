<?php

namespace XApi\LrsBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * @author Christian Flothmann <christian.flothmann@xabbuh.de>
 */
final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('xapi');

        $treeBuilder
            ->getRootNode()
            ->beforeNormalization()
            ->ifTrue(static fn(array $v): bool => isset($v['type']) && $v['type'] === 'orm' && !isset($v['object_manager_service']))
            ->thenInvalid('You need to configure the object manager service when the repository type is "mongodb" or "orm".')
            ->end()
            ->children()
            ->enumNode('type')
            ->isRequired()
            ->values(['in_memory', 'mongodb', 'orm'])
            ->end()
            ?->scalarNode('object_manager_service')->end()
            ?->end()
            ->end();

        return $treeBuilder;
    }
}
