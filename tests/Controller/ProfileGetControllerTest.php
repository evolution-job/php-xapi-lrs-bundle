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
final class ProfileGetControllerTest extends WebTestCase
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

    public function testGetActivityProfileAndProfileList(): void
    {
        $path = '/activities/profile?activityId='.rawurlencode('https://example.org/activity').'&profileId=resume';
        $this->request('PUT', $path, '{"page":4}', 'application/json');
        self::assertSame(204, $this->client->getResponse()->getStatusCode());

        $this->request('GET', $path);
        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        self::assertSame('{"page":4}', $this->client->getResponse()->getContent());
        self::assertSame('application/json', $this->client->getResponse()->headers->get('Content-Type'));

        $this->request('GET', '/activities/profile?activityId='.rawurlencode('https://example.org/activity'));
        self::assertJsonStringEqualsJsonString('["resume"]', $this->client->getResponse()->getContent());

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
