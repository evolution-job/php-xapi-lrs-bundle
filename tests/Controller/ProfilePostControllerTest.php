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
use XApi\Fixtures\Json\ActorJsonFixtures;
use XApi\LrsBundle\Tests\App\TestingKernel;

/**
 * @author Mathieu Boldo <mathieu.boldo@entrili.com>
 */
final class ProfilePostControllerTest extends WebTestCase
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

    public function testPostAgentProfileMergesJson(): void
    {
        $path = '/agents/profile?agent='.rawurlencode(ActorJsonFixtures::getTypicalAgent()).'&profileId=preferences';
        $this->request('PUT', $path, '{"theme":"light","retained":true}', 'application/json');
        $this->request('POST', $path, '{"theme":"dark"}', 'application/json');

        self::assertSame(204, $this->client->getResponse()->getStatusCode());

        $this->request('GET', $path);
        self::assertJsonStringEqualsJsonString('{"theme":"dark","retained":true}', $this->client->getResponse()->getContent());
    }

    private function request(string $method, string $uri, ?string $content = null, ?string $contentType = null): void
    {
        $server = ['HTTP_X_EXPERIENCE_API_VERSION' => '1.0.3'];
        if (null !== $contentType) {
            $server['CONTENT_TYPE'] = $contentType;
        }

        $this->client->request($method, $uri, [], [], $server, $content);
    }
}
