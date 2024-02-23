LRS Bundle
==========

This Symfony bundle helps to generate a Learning Record Store, as defined by the xAPI (or Tin Can API).

The configuration guide proposed below use **Doctrine** ORM.

Versions
--------

 - V0.2: add old pull-requests from Jérôme Parmentier (Lctrs),  update dependencies to ^V3.0, use Entrili GIT repositories
 - V0.1: Symfony 4.4, Php 7.1, original files from php-xapi/* repositories

Installation
------------

- COMPOSER: add repositories and requirements into `composer.json` of your project
```
    "repositories": [
        {
            "type": "git",
            "url": "https://github.com/evolution-job/php-xapi-lrs-bundle.git"
        },
        {
            "type": "git",
            "url": "https://github.com/evolution-job/php-xapi-repository-api.git"
        },
        {
            "type": "git",
            "url": "https://github.com/evolution-job/php-xapi-repository-doctrine.git"
        },
        {
            "type": "git",
            "url": "https://github.com/evolution-job/php-xapi-repository-doctrine-orm.git"
        },
        {
            "type": "git",
            "url": "https://github.com/evolution-job/php-xapi-serializer.git"
        },
        {
            "type": "git",
            "url": "https://github.com/evolution-job/php-xapi-symfony-serializer.git"
        }
    ],
    "require": {
        ...,
        "php-xapi/lrs-bundle": "^0.2"
    }
```

- launch `composer install -W` to download required libraries
- **ADD BUNDLE:** to `app/AppKernel.php` in your application
```
    $bundles = [
        ...
        new XApi\LrsBundle\XApiLrsBundle(),
    ];
```

- **BUNDLE CONFIGURATION**: add `xapi.yaml` to `./config/packages/`
```
    xapi_lrs:
        type: orm
        object_manager_service: doctrine.orm.entity_manager
```

- **ROUTING**: Add this to your routes ( `./config/routes.yaml` )
```
    xapi:
        resource: "@XApiLrsBundle/Resources/config/routing.xml"
        prefix:   /lrs
```

- **DOCTRINE ORM CONFIGURATION**: add to `./config/packages/doctrine.yaml`
```
    doctrine:
        orm:
            auto_generate_proxy_classes: true
            naming_strategy: doctrine.orm.naming_strategy.underscore_number_aware
            auto_mapping: true
            quote_strategy: '@xapi_lrs.doctrine.orm.quote.strategy'
            mappings:
                XApiLrsBundle:
                    mapping: true
                    type: xml
                    dir: '%kernel.root_dir%/../vendor/php-xapi/repository-doctrine-orm/metadata'
                    is_bundle: false
                    prefix: XApi\Repository\Doctrine\Mapping
```

- Update your **database** with command `php bin/console doctrine:schema:update`

Endpoints
---------

- /lrs/activity
- /lrs/activity/state
- /lrs/statements
