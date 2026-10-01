<?php

namespace XApi\LrsBundle\DependencyInjection;

use InvalidArgumentException;
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
                ?->scalarNode('object_manager_service')
                ->end()
                ->arrayNode('allowed_origins')
                    ->scalarPrototype()->end()
                    ->defaultValue(['self'])
                    ->beforeNormalization()
                    ->ifString()
                    ->then(static function (string $value): array { return [$value]; })
                    ->end()
                    ->validate()
                    ->always(function (array $values): array {
                        foreach ($values as $origin) {
                            if (false === filter_var($origin, FILTER_VALIDATE_URL)) {
                                throw new InvalidArgumentException(sprintf('Invalid domain "%s".', $origin));
                            }
                            $domain = parse_url($origin, PHP_URL_HOST);
                            if (false === filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
                                throw new InvalidArgumentException(sprintf('Invalid domain "%s".', $origin));
                            }
                        }

                        return $values;
                    })
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
