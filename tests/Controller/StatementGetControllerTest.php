<?php

/*
 * This file is part of the xAPI package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace XApi\LrsBundle\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use XApi\LrsBundle\Tests\App\TestingKernel;

/**
 * @author Mathieu Boldo <mathieu.boldo@entrili.com>
 */
class StatementGetControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    protected static function getKernelClass(): string
    {
        return TestingKernel::class;
    }

    protected function setUp(): void
    {
        $this->client = self::createClient();
    }

    public function testGetStatementsBadOrigin(): void
    {
        $host = 'https://learning.repository.example.com';

        $this->client->request(
            'GET',
            '/statements',
            [],
            [],
            [
                'HTTP_X-Experience-API-Version' => '1.0',
                'HTTP_Origin'                   => 'http://evil.com',
            ]
        );

        $response = $this->client->getResponse();

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());

        // Headers
        $this->assertNull($response->headers->get('Access-Control-Allow-Origin'));
        $this->assertSame('1.0.3', $response->headers->get('X-Experience-API-Version'));
        $this->assertSame('ETag, Last-Modified, X-Experience-API-Version, X-Experience-API-Consistent-Through', $response->headers->get('Access-Control-Expose-Headers'));
        $this->assertSame('Origin', $response->headers->get('Vary'));
        $this->assertSame('true', $response->headers->get('Access-Control-Allow-Credentials'));
        $this->assertNull($response->headers->get('Content-Security-Policy'));
        $this->assertTrue($response->headers->has('X-Experience-API-Consistent-Through'));

        // Content
        $this->assertJson($response->getContent());
        $data = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertArrayHasKey('statements', $data);
        $this->assertArrayHasKey('more', $data);
        $this->assertIsArray($data['statements']);
        $this->assertIsString($data['more']);
    }

    public function testGetStatementsListCompliance(): void
    {
        $host = 'https://learning.repository.example.com';

        $this->client->request(
            'GET',
            '/statements',
            [],
            [],
            [
                'HTTP_X-Experience-API-Version' => '1.0.3',
                'HTTP_Origin'                   => $host,
            ]
        );

        $response = $this->client->getResponse();

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());

        // Headers
        $this->assertSame($host, $response->headers->get('Access-Control-Allow-Origin'));
        $this->assertSame('1.0.3', $response->headers->get('X-Experience-API-Version'));
        $this->assertSame('ETag, Last-Modified, X-Experience-API-Version, X-Experience-API-Consistent-Through', $response->headers->get('Access-Control-Expose-Headers'));
        $this->assertSame('Origin', $response->headers->get('Vary'));
        $this->assertSame('true', $response->headers->get('Access-Control-Allow-Credentials'));
        $this->assertSame(sprintf('frame-ancestors %s', $host), $response->headers->get('Content-Security-Policy'));
        $this->assertTrue($response->headers->has('X-Experience-API-Consistent-Through'));

        // Content
        $this->assertJson($response->getContent());
        $data = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertArrayHasKey('statements', $data);
        $this->assertArrayHasKey('more', $data);
        $this->assertIsArray($data['statements']);
        $this->assertIsString($data['more']);
    }
}
