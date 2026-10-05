<?php

declare(strict_types=1);

/*
 * This file is part of the xAPI package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace XApi\LrsBundle\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use XApi\LrsBundle\Tests\App\TestingKernel;

/**
 * @author Mathieu Boldo <mathieu.boldo@entrili.com>
 */
final class AboutGetControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    protected static function getKernelClass(): string
    {
        return TestingKernel::class;
    }

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->client->disableReboot();
    }

    public function testAboutListsTheSupportedVersion(): void
    {
        $server = ['HTTP_X_EXPERIENCE_API_VERSION' => '1.0.3'];
        $this->client->request('GET', '/about', [], [], $server);

        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        self::assertJsonStringEqualsJsonString('{"version":["1.0.3"]}', $this->client->getResponse()->getContent());
    }
}
