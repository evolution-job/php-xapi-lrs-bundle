<?php

namespace XApi\LrsBundle\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use XApi\Fixtures\Json\StatementJsonFixtures;
use XApi\LrsBundle\Tests\App\TestingKernel;

/**
 * @author Mathieu Boldo <mathieu.boldo@entrili.com>
 */
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
     * Conform minimal unique Statement
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

    public function testPostSingleStatementWithoutIdReturnsLrsAssignedUuid(): void
    {
        $statement = json_decode(StatementJsonFixtures::getMinimalStatement(), true, 512, JSON_THROW_ON_ERROR);
        unset($statement['id']);

        $this->executePostRequest(json_encode($statement, JSON_THROW_ON_ERROR));
        $response = $this->client->getResponse();

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $ids = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertCount(1, $ids);
        $this->assertMatchesRegularExpression('/\A[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/', $ids[0]);
    }

    /**
     * Conform maximal unique Statement
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
     * Collection of Statements
     */
    public function testPostCollectionOfStatements(): void
    {
        $jsonPayload = StatementJsonFixtures::getStatementCollection();

        $this->executePostRequest($jsonPayload);
        $response = $this->client->getResponse();

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertJson($response->getContent());

        $responseData = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($responseData);

        $this->assertContains('12345678-1234-5678-8234-567812345678', $responseData);
        $this->assertContains('12345678-1234-5678-8234-567812345679', $responseData);
    }

    /**
     * Bad JSON
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
     * Validation POST Method Tunneling xAPI (POST -> PUT)
     */
    public function testPostRequestWithPutTunneling(): void
    {
        $jsonPayload = StatementJsonFixtures::getMinimalStatement();

        $parameters = [
            'statementId'              => '12345678-1234-5678-8234-567812345678',
            'X-Experience-API-Version' => '1.0.3',
            'Content-Type'             => 'application/json',
            'content'                  => $jsonPayload,
        ];

        // xAPI specs required a statementId for PUT
        $this->client->request(
            'POST',
            '/statements?method=PUT',
            $parameters,
            [],
            [
                'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
            ]
        );

        $response = $this->client->getResponse();

        $this->assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    /**
     * POST standard (no alternate syntax)
     */
    public function testStandardPostRequestIsUnchanged(): void
    {
        $jsonPayload = StatementJsonFixtures::getMinimalStatement();
        $this->executePostRequest($jsonPayload);

        $this->assertNotEquals(Response::HTTP_BAD_REQUEST, $this->client->getResponse()->getStatusCode());
    }

    /**
     * xAPI alternate syntax
     * POST request simulating GET
     */
    public function testAlternateSyntaxGetMethodTransformation(): void
    {
        $formData = [
            'statementId' => '9f137021-39b5-4b5c-959c-851f8245785a',
            'attachments' => 'true',
        ];

        $this->client->request(
            'POST',
            '/statements?method=GET',
            $formData, // application/x-www-form-urlencoded
            [],
            [
                'CONTENT_TYPE'                  => 'application/x-www-form-urlencoded',
                'HTTP_X-Experience-API-Version' => '1.0.2',
            ]
        );

        $request = $this->client->getRequest();

        $this->assertSame('GET', $request->getMethod());
        $this->assertTrue($request->query->has('statementId'));
        $this->assertSame('9f137021-39b5-4b5c-959c-851f8245785a', $request->query->get('statementId'));
        $this->assertSame('true', $request->query->get('attachments'));
    }

    /**
     * xAPI alternate syntax
     * POST simulationg a PUT with JSON content.
     */
    public function testAlternateSyntaxPutMethodWithContentTransformation(): void
    {
        $originalJsonContent = StatementJsonFixtures::getAllPropertiesStatement();

        $formData = [
            'content'                  => $originalJsonContent,
            'Content-Type'             => 'application/json', // Simulate Header
            'X-Experience-API-Version' => '1.0.2',
        ];

        $this->client->request(
            'POST',
            '/statements?method=PUT',
            $formData,
            [],
            ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']
        );

        $request = $this->client->getRequest();

        $this->assertSame('PUT', $request->getMethod());
        $this->assertSame('application/json', $request->headers->get('Content-Type'));
        $this->assertSame($originalJsonContent, $request->getContent());
    }

    /**
     * Invalid method behavior
     */
    public function testAlternateSyntaxWithUnsupportedMethod(): void
    {
        $this->client->request(
            'POST',
            '/statements?method=PATCH',
            ['content' => 'dummy']
        );

        $this->assertSame(Response::HTTP_BAD_REQUEST, $this->client->getResponse()->getStatusCode());
    }

    /**
     * Tunneling with Attachment
     */
    public function testPostRequestWithPutTunnelingAndAttachment(): void
    {
        $statementId = '12345678-1234-5678-8234-567812345678';

        $jsonPayload = StatementJsonFixtures::getAllPropertiesStatement();

        // Creation of a binary file to attach on the fly
        $filePath = tempnam(sys_get_temp_dir(), 'xapi_');
        file_put_contents($filePath, 'some text content');
        $uploadedFile = new UploadedFile($filePath, 'attachment.txt', 'text/plain', null, true);

        $parameters = [
            'statementId'              => $statementId,
            'X-Experience-API-Version' => '1.0.3',
            'Content-Type'             => 'multipart/mixed', // Mention of the internal payload using attachments
            'content'                  => $jsonPayload,
        ];

        $files = [
            'attachment' => $uploadedFile, // Binary file to attach on the fly
        ];

        $this->client->request(
            'POST',
            '/statements?method=PUT',
            $parameters,
            $files,
            ['CONTENT_TYPE' => 'multipart/form-data']
        );

        $response = $this->client->getResponse();
        $this->assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    /**
     * Query String should only contain "method" parameter
     */
    public function testPostTunnelingFailsIfQueryStringHasExtraParameters(): void
    {
        $jsonPayload = StatementJsonFixtures::getMinimalStatement();
        $statementId = '12345678-1234-5678-8234-567812345678';

        // VIOLATION : Présence de 'statementId' dans la query string (l'URL)
        $this->client->request(
            'POST',
            '/statements?method=PUT&statementId='.$statementId,
            [
                'content' => $jsonPayload,
            ],
            [],
            [
                'CONTENT_TYPE'                  => 'application/x-www-form-urlencoded',
                'HTTP_X-Experience-API-Version' => '1.0.3',
            ]
        );

        $response = $this->client->getResponse();

        // Le listener doit interdire la requête et lever une exception se traduisant par un 400 Bad Request
        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    /**
     * StatementId should match the ID in the JSON body
     */
    public function testPostTunnelingFailsIfStatementIdDoesNotMatchBodyId(): void
    {
        $jsonPayload = StatementJsonFixtures::getMinimalStatement(); // ID interne : ex '12345678-1234-5678-8234-567812345678'
        $wrongStatementId = '99999999-9999-9999-9999-999999999999';

        // VIOLATION : L'ID passé en paramètre de formulaire ne correspond pas à l'ID à l'intérieur du JSON 'content'
        $this->client->request(
            'POST',
            '/statements?method=PUT',
            [
                'statementId'              => $wrongStatementId,
                'X-Experience-API-Version' => '1.0.3',
                'Content-Type'             => 'application/json',
                'content'                  => $jsonPayload,
            ],
            [],
            [
                'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
            ]
        );

        $response = $this->client->getResponse();

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    /**
     * StatementId should match the ID in the JSON body
     */
    public function testPostTunnelingFailsIfStatementIdIsMissingOnPut(): void
    {
        $jsonPayload = StatementJsonFixtures::getMinimalStatement();

        // VIOLATION : Le paramètre obligatoire 'statementId' est totalement absent de la requête
        $this->client->request(
            'POST',
            '/statements?method=PUT',
            [
                'X-Experience-API-Version' => '1.0.3',
                'Content-Type'             => 'application/json',
                'content'                  => $jsonPayload,
            ],
            [],
            [
                'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
            ]
        );

        $response = $this->client->getResponse();

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
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
