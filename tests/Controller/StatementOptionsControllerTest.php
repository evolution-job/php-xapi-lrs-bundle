<?php

namespace XApi\LrsBundle\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use XApi\LrsBundle\Tests\App\TestingKernel;

/**
 * @author Mathieu Boldo <mathieu.boldo@entrili.com>
 */
class StatementOptionsControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    protected static function getKernelClass(): string
    {
        return TestingKernel::class;
    }

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    /**
     * Test Case : OPTIONS request
     */
    public function testOptionsGlobalStatementsEndpoint(): void
    {
        $host = 'https://learning.repository.example.com';
        $this->client->request('OPTIONS', '/statements', [], [],  [
            'HTTP_Origin'                   => $host,
        ]);

        $response = $this->client->getResponse();

        // Headers
        $this->assertSame($host, $response->headers->get('Access-Control-Allow-Origin'));
        $this->assertSame('1.0.3', $response->headers->get('X-Experience-API-Version'));
        $this->assertSame('ETag, Last-Modified, X-Experience-API-Version, X-Experience-API-Consistent-Through', $response->headers->get('Access-Control-Expose-Headers'));
        $this->assertSame('Origin', $response->headers->get('Vary'));
        $this->assertSame('true', $response->headers->get('Access-Control-Allow-Credentials'));
        $this->assertSame(sprintf('frame-ancestors %s', $host), $response->headers->get('Content-Security-Policy'));
        $this->assertSame('GET, POST, PUT, DELETE, HEAD, OPTIONS', $response->headers->get('Allow'));
        $this->assertSame('Accept, Authorization, Content-Type, If-Match, If-None-Match, X-Experience-API-Version', $response->headers->get('Access-Control-Allow-Headers'));
        $this->assertSame('GET, POST, PUT, DELETE, HEAD, OPTIONS', $response->headers->get('Access-Control-Allow-Methods'));
        $this->assertSame('86400', $response->headers->get('Access-Control-Max-Age'));

        $this->assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        $this->assertTrue($response->headers->has('Allow'));
        $this->assertStringContainsString('POST', $response->headers->get('Allow'));
    }
}
