CHANGELOG
=========

0.6.2
-----

- fix `VersionListener`: requests with method OPTIONS don't need to include `X-Experience-API-Version` header

0.6.1
-----

- dropped `xapi_lrs.doctrine.orm.quote.strategy` service
- dropped support for `xapi/repository-doctrine-orm` < `0.5`

0.6.0
-----

- dropped support for Symfony < `7.4`

0.5.0
-----

- dropped support for PHP < `8.4`
- dropped support for Symfony < `7.3`

0.4.0
-----

- dropped support for PHP < `8.1`
- dropped support for Symfony < `6.4`

0.3.0
-----

- dropped support for PHP < `8.1`
- dropped support for Symfony < `5.4`
- added Service `xapi_lrs.doctrine.orm.quote.strategy` updated (`QuoteStrategy` from xapi/repository-doctrine-orm)
- All dependencies from php-xapi/* are now loaded from forks at `https://github.com/evolution-job/`

0.2.0
-----

- add old pull-requests from Jérôme Parmentier (Lctrs). Sources are from `php-xapi/*` repositories / Discussions

0.1.0
-----

- PHP `7.1`
- Symfony `4.4`
- Original files from `php-xapi/*` repositories
- Work with Doctrine ORM: `php-xapi/repository-doctrine-orm`