# Development guide

This repository is the `php-xapi/lrs-bundle` Symfony bundle from [evolution-job/php-xapi-lrs-bundle](https://github.com/evolution-job/php-xapi-lrs-bundle). It provides xAPI Learning Record Store endpoints and Symfony integration. The package targets PHP 8.4+ and Symfony 7.4+; its repository, serializer, and xAPI model integrations are separate `php-xapi/*` Composer packages.

## Environment and dependencies

The package requirements and the `php-xapi/*` VCS repositories are declared in `composer.json`; `composer.lock` records the resolved dependency versions. The bundle does not define Composer scripts. Use the `vendor/bin` tools directly. CI currently runs on PHP 8.4, installs dependencies with `composer update --no-progress --prefer-stable`, then runs PhpSpec and PHPUnit. For local reproducibility, prefer `composer install` against the lock file.

## Tests

There are two complementary test suites:

- `spec/` contains PhpSpec specifications.
- `tests/Controller`, `tests/EventListener`, and related directories contain PHPUnit tests, including Symfony functional tests bootstrapped by `tests/App/TestingKernel.php`. `phpunit.xml.dist` also includes the `vendor/php-xapi/*/tests` suites when present.

In the WSL2 development environment, PHP runs in the `php8_4` Docker container and the project is mounted at `/var/projects/lrs-bundle`. Run both suites from the repository root inside that container:

```sh
docker exec php8_4 sh -lc 'cd /var/projects/lrs-bundle && vendor/bin/phpspec run'
docker exec php8_4 sh -lc 'cd /var/projects/lrs-bundle && SYMFONY_DEPRECATIONS_HELPER=weak vendor/bin/phpunit'
```

Run a focused PHPUnit test by naming its file or select a test with PHPUnit's `--filter` option:

```sh
docker exec php8_4 sh -lc 'cd /var/projects/lrs-bundle && vendor/bin/phpunit tests/Controller/StatementOptionsControllerTest.php'
```

Add a PHPUnit test under the matching `tests/` namespace/path (`XApi\LrsBundle\Tests\...`) and use PHPUnit's `TestCase` for unit tests or Symfony's `WebTestCase` for requests through the test kernel. Functional tests should use the in-memory repositories configured by `TestingKernel` unless persistence behavior is what the test covers. Add PhpSpec examples under `spec/` with the matching `spec\XApi\LrsBundle\...` namespace for object behavior, and use the existing `ObjectBehavior` conventions. Run the focused test while iterating, then run both complete suites before submitting.

A minimal PHPUnit smoke test for the xAPI JSON response can be placed at `tests/Controller/JsonXapiResponseTest.php`:

```php
<?php

/*
 * This file is part of the xAPI package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace XApi\LrsBundle\Tests\Controller;

use PHPUnit\Framework\TestCase;
use XApi\LrsBundle\Response\JsonResponse;

class JsonXapiResponseTest extends TestCase
{
    public function testResponseIncludesTheXapiVersionAndJsonBody(): void
    {
        $response = new JsonResponse(['ok' => true]);

        self::assertSame('1.0.3', $response->headers->get('X-Experience-API-Version'));
        self::assertJsonStringEqualsJsonString('{"ok":true}', $response->getContent());
    }
}
```

Run that example with:

```sh
docker exec php8_4 sh -lc 'cd /var/projects/lrs-bundle && vendor/bin/phpunit tests/Controller/JsonXapiResponseTest.php'
```

The equivalent temporary smoke test was created and run successfully during preparation of this guide (1 test, 4 assertions); the temporary test file was removed afterward.

## Implementation and configuration notes

- Production classes use the `XApi\LrsBundle\` PSR-4 namespace and live under `src/`. Bundle services and routing are declared in `src/Resources/config/`; extension configuration and loading are in `src/DependencyInjection/`.
- The `xapi_lrs` extension requires a `type` of `in_memory`, `mongodb`, or `orm`. Doctrine-backed types also require `object_manager_service`; `allowed_origins` defaults to `['self']` and is validated as URL origins. Keep configuration behavior and service aliases in `Configuration` and `XApiLrsExtension` aligned.
- PHPUnit functional tests load the bundle's routing configuration using `TestingKernel` and fake repositories. Keep requests, xAPI headers, and response assertions consistent with the existing controller tests.
- Statement POST accepts JSON statements and batches, as well as native `multipart/mixed` requests containing a JSON first part and attachment parts. Attachment bytes must remain unmodified and must match their `X-Experience-API-Hash` SHA-256 header. Keep this distinct from alternate POST tunneling, which has separate form-data handling.
- State request deserialization validates method-specific query parameters. State PUT permits requests without concurrency headers, but enforces `If-Match` and `If-None-Match` when supplied. Preserve these protocol details when changing the State routes.
- Activity GET returns an Activity object containing the requested ID when no canonical Activity definition is stored; it should not return 404 solely because the definition is unknown.
- xAPI error responses must retain the required version and consistent-through headers. Preserve xAPI response headers and HTTP semantics in controllers, listeners, and exception handling.
- Follow the existing PHP formatting and strict typing patterns in the surrounding file. Rector is configured in `rector.php` for `spec/` and `src/`, including PHP 8.4, PHPUnit 11, and code-quality/style sets. Its invocation is noted at the end of that configuration file; inspect diffs before accepting automated changes.
- The xAPI HTTP behavior implemented here follows the [xAPI Communication specification](https://github.com/adlnet/xAPI-Spec/blob/master/xAPI-Communication.md); preserve required headers, status codes, request semantics, and response formats when changing controllers or listeners.

## xAPI scope and TODO

The current routes cover Statements, State, and Activities. The required Agents, Profile, and About resources are not implemented yet; add their routes and corresponding behavior/tests in a future scope.
