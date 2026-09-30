<?php

namespace XApi\LrsBundle\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use XApi\LrsBundle\Tests\App\TestingKernel;

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
        $this->client->request('OPTIONS', '/statements');

        $response = $this->client->getResponse();

        $this->assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        $this->assertTrue($response->headers->has('Allow'));
        $this->assertStringContainsString('POST', $response->headers->get('Allow'));
    }
}
