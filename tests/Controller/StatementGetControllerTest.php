<?php

namespace XApi\LrsBundle\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use XApi\LrsBundle\Tests\App\TestingKernel;

class StatementGetControllerTest extends WebTestCase
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

    public function testGetStatementsListCompliance(): void
    {
        $this->client->request(
            'GET',
            '/statements',
            [],
            [],
            ['HTTP_X-Experience-API-Version' => '1.0.3']
        );

        $response = $this->client->getResponse();

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertTrue($response->headers->has('X-Experience-API-Version'));

        $this->assertTrue($response->headers->has('X-Experience-API-Consistent-Through'));

        $this->assertJson($response->getContent());
        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('statements', $data);
        $this->assertArrayHasKey('more', $data);
        $this->assertIsArray($data['statements']);
        $this->assertIsString($data['more']);
    }
}
