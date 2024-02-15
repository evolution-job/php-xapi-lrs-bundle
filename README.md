LRS Bundle
==========

This Symfony bundle helps to generate a Learning Record Store, as defined by the xAPI (or Tin Can API).

The configuration guide proposed below is use **Doctrine** ORM.

Versions
--------

 - V0.1: Symfony 3.4, Php 7.1, original files from php-xapi/* repositories


Installation
------------

- COMPOSER: add the repository and require to `composer.json` of your project
```
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/php-xapi/lrs-bundle"
        }
    ],
    "require": {
        ...,
        "php-xapi/repository-doctrine": "^0.3.x-dev",
        "php-xapi/lrs-bundle": "0.1.x-dev"
    }
```
(replace php-xapi by your own user if you have forked the project)

launch `composer update` to download corresponding libraries

- **ADD BUNDLE:** add the bundle to `app/AppKernel.php` in your application
```
    $bundles = [
        ...
        new XApi\LrsBundle\XApiLrsBundle(),
    ];
```
- **ROUTING**: Add this to your routes ( `./config/routes.yaml` )
```
    xapi:
    resource: "@XApiLrsBundle/Resources/config/routing.xml"
    prefix:   /lrs
```
- **CONFIG BUNDLE**: add `xapi.yaml` to `./config/packages/`
```
xapi_lrs:
    type: orm
    object_manager_service: doctrine.orm.entity_manager

services:
    # default configuration for services in *this* file
    _defaults:
        autoconfigure: true # Automatically registers your services as commands, event subscribers, etc.

    # Controllers from the bundle are not autowired: tag them as services
    xapi_lrs.controller.statement.get:
        class: XApi\LrsBundle\Controller\StatementGetController
        tags: [ 'controller.service_arguments' ]
        arguments:
            - '@xapi_lrs.repository.statement'
            - '@xapi_lrs.statement.serializer'
            - '@xapi_lrs.statement_result.serializer'
            - '@xapi_lrs.factory.statements_filter'

    xapi_lrs.controller.statement.post:
        class: XApi\LrsBundle\Controller\StatementPostController
        tags: [ 'controller.service_arguments' ]

    xapi_lrs.controller.statement.put:
        class: XApi\LrsBundle\Controller\StatementPutController
        tags: [ 'controller.service_arguments' ]
        arguments:
            - '@xapi_lrs.repository.statement'

    # Factory: Fix mismatched service name (clone xapi_lrs.serializer_factory )
    xapi_lrs.serializer.factory:
        class: Xabbuh\XApi\Serializer\Symfony\SerializerFactory
        arguments:
            - '@xapi_lrs.serializer'

    # Repository: see why in vendor/php-xapi/repository-doctrine/CHANGELOG.md
    xapi_lrs.doctrine.class_metadata:
        class: Doctrine\ORM\Mapping\ClassMetadata
        arguments:
            - 'XApi\Repository\Doctrine\Mapping\Statement'
        factory: ['@xapi_lrs.doctrine.object_manager', 'getClassMetadata']

    xapi_lrs.repository.mapped_statement:
        class: XApi\Repository\ORM\StatementRepository
        arguments:
            - '@xapi_lrs.doctrine.object_manager'
            - '@xapi_lrs.doctrine.class_metadata'
```