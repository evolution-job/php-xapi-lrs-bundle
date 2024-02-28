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
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

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

        $xmlFileLoader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));

        $xmlFileLoader->load('controller.xml');
        $xmlFileLoader->load('event_listener.xml');
        $xmlFileLoader->load('factory.xml');
        $xmlFileLoader->load('serializer.xml');

        switch ($config['type']) {
            case 'in_memory':
                break;
            case 'mongodb':
                $xmlFileLoader->load('doctrine.xml');
                $xmlFileLoader->load('mongodb.xml');

                $container->setAlias('xapi_lrs.doctrine.object_manager', $config['object_manager_service']);
                $container->setAlias('xapi_lrs.repository.activity', 'xapi_lrs.repository.activity.doctrine');
                $container->setAlias('xapi_lrs.repository.state', 'xapi_lrs.repository.state.doctrine');
                $container->setAlias('xapi_lrs.repository.statement', 'xapi_lrs.repository.statement.doctrine');
                break;
            case 'orm':
                $xmlFileLoader->load('doctrine.xml');
                $xmlFileLoader->load('orm.xml');

                $container->setAlias('xapi_lrs.doctrine.object_manager', $config['object_manager_service']);
                $container->setAlias('xapi_lrs.repository.activity', 'xapi_lrs.repository.activity.doctrine');
                $container->setAlias('xapi_lrs.repository.state', 'xapi_lrs.repository.state.doctrine');
                $container->setAlias('xapi_lrs.repository.statement', 'xapi_lrs.repository.statement.doctrine');
                break;
        }
    }

    public function getAlias(): string
    {
        return 'xapi_lrs';
    }
}
