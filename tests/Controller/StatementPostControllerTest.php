<?php

namespace XApi\LrsBundle\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use XApi\Fixtures\Json\StatementJsonFixtures;
use XApi\LrsBundle\Tests\App\TestingKernel;

class StatementPostControllerTest extends WebTestCase
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
     * Test Case : Conform minimal unique Statement
     */
    public function testPostSingleMinimalStatement(): void
    {
        $jsonPayload = StatementJsonFixtures::getMinimalStatement();

        $this->executePostRequest($jsonPayload);
        $response = $this->client->getResponse();

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertJson($response->getContent());
        $responseData = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertIsArray($responseData);
        $this->assertContains('12345678-1234-5678-8234-567812345678', $responseData);
        $this->assertNotContains('12345678-1234-5678-8234-567812345679', $responseData);
    }

    /**
     * Test Case : Conform maximal unique Statement
     */
    public function testPostSingleTypicalStatement(): void
    {
        $jsonPayload = StatementJsonFixtures::getTypicalStatement();

        $this->executePostRequest($jsonPayload);
        $response = $this->client->getResponse();

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertJson($response->getContent());
        $responseData = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertIsArray($responseData);
        $this->assertContains('12345678-1234-5678-8234-567812345678', $responseData);
        $this->assertNotContains('12345678-1234-5678-8234-567812345679', $responseData);
    }

    /**
     * Test Case : Collection of Statements
     */
    public function testPostCollectionOfStatements(): void
    {
        $jsonPayload = StatementJsonFixtures::getStatementCollection();

        $this->executePostRequest($jsonPayload);
        $response = $this->client->getResponse();

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertJson($response->getContent());

        $responseData = json_decode($response->getContent(), true);
        $this->assertIsArray($responseData);

        $this->assertContains('12345678-1234-5678-8234-567812345678', $responseData);
        $this->assertContains('12345678-1234-5678-8234-567812345679', $responseData);
    }

    /**
     * Test Case : Bad JSON
     */
    public function testPostMalformedJsonShouldReturnBadRequest(): void
    {
        $invalidPayload = '[{"id": "eaf1c3e2-be78-434a-ab70-4790b07f4c64"';

        $this->client->catchExceptions(true);
        $this->executePostRequest($invalidPayload);
        $response = $this->client->getResponse();

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());

        $this->assertStringContainsString(
            'The content of the request cannot be deserialized into a valid xAPI statement.',
            $response->getContent()
        );
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

    /**
     * Test case 6 : validation POST Method Tunneling xAPI (POST -> PUT)
     */
    public function testPostRequestWithPutTunneling(): void
    {
        $jsonPayload = StatementJsonFixtures::getMinimalStatement();
        $statementId = '12345678-1234-5678-8234-567812345678';

        // xAPI specs required a statementId for PUT
        $this->client->request(
            'POST',
            '/statements?method=PUT&statementId='.$statementId,
            [],
            [],
            [
                'CONTENT_TYPE'                  => 'application/json',
                'HTTP_X-Experience-API-Version' => '1.0.3',
            ],
            $jsonPayload
        );

        $response = $this->client->getResponse();

        $this->assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    private function executePostRequest(string $payload): void
    {
        $this->client->request(
            'POST',
            '/statements',
            [],
            [],
            [
                'CONTENT_TYPE'                  => 'application/json',
                'HTTP_X-Experience-API-Version' => '1.0.2',
            ],
            $payload
        );
    }
}
