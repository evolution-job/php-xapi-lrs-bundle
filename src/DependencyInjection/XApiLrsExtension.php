<?php

/*
 * This file is part of the xAPI package.
 *
 * (c) Christian Flothmann <christian.flothmann@xabbuh.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace XApi\LrsBundle\DependencyInjection;

use Exception;
use Override;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 * @author Christian Flothmann <christian.flothmann@xabbuh.de>
 */
final class XApiLrsExtension extends Extension
{
    /**
     * @throws Exception
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $yamlFileLoader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));

        $yamlFileLoader->load('controller.yaml');
        $yamlFileLoader->load('event_listener.yaml');
        $yamlFileLoader->load('factory.yaml');
        $yamlFileLoader->load('serializer.yaml');

        switch ($config['type']) {
            case 'in_memory':
                break;
            case 'mongodb':
                $yamlFileLoader->load('doctrine.yaml');
                
                $container->setAlias('xapi_lrs.doctrine.object_manager', $config['object_manager_service']);
                $container->setAlias('xapi_lrs.repository.activity', 'xapi_lrs.repository.activity.doctrine');
                $container->setAlias('xapi_lrs.repository.state', 'xapi_lrs.repository.state.doctrine');
                $container->setAlias('xapi_lrs.repository.statement', 'xapi_lrs.repository.statement.doctrine');
                break;
            case 'orm':
                $yamlFileLoader->load('doctrine.yaml');
                $yamlFileLoader->load('orm.yaml');

                $container->setAlias('xapi_lrs.doctrine.object_manager', $config['object_manager_service']);
                $container->setAlias('xapi_lrs.repository.activity', 'xapi_lrs.repository.activity.doctrine');
                $container->setAlias('xapi_lrs.repository.state', 'xapi_lrs.repository.state.doctrine');
                $container->setAlias('xapi_lrs.repository.statement', 'xapi_lrs.repository.statement.doctrine');
                break;
        }
    }

    #[Override]
    public function getAlias(): string
    {
        return 'xapi_lrs';
    }
}
