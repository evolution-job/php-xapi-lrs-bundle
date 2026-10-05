<?php

namespace XApi\LrsBundle\Tests\Controller;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Router;
use Symfony\Contracts\Cache\CacheInterface;
use Xabbuh\XApi\Common\Exception\BadRequestException;
use Xabbuh\XApi\Common\Exception\NotFoundException;
use Xabbuh\XApi\DataFixtures\StatementFixtures;
use Xabbuh\XApi\Model\Activity;
use Xabbuh\XApi\Model\IRI;
use Xabbuh\XApi\Model\LanguageMap;
use Xabbuh\XApi\Model\StatementReference;
use Xabbuh\XApi\Model\StatementResult;
use Xabbuh\XApi\Model\Verb;
use Xabbuh\XApi\Serializer\ActivitySerializerInterface;
use Xabbuh\XApi\Serializer\ActorSerializerInterface;
use Xabbuh\XApi\Serializer\Exception\ActorDeserializationException;
use Xabbuh\XApi\Serializer\StatementResultSerializerInterface;
use Xabbuh\XApi\Serializer\StatementSerializerInterface;
use XApi\LrsBundle\Controller\StatementGetController;
use XApi\LrsBundle\Model\StatementsFilterFactory;
use XApi\LrsBundle\Service\StatementContinuationManager;
use XApi\LrsBundle\Service\StatementFormatNormalizer;
use XApi\LrsBundle\Service\StatementGetQueryValidator;
use XApi\Repository\Api\ActivityRepositoryInterface;
use XApi\Repository\Api\StatementRepositoryInterface;
use XApi\Repository\Api\VerbRepositoryInterface;

/**
 * @author Mathieu Boldo <mathieu.boldo@entrili.com>
 */
class StatementGetPaginationTest extends TestCase
{
    public function testMalformedFilterValuesAreRejectedAsBadRequests(): void
    {
        $invalidParameters = [
            'statementId' => 'not-a-uuid',
            'voidedStatementId' => 'not-a-uuid',
            'registration' => 'not-a-uuid',
            'activity' => 'not an IRI',
            'verb' => 'not an IRI',
            'ascending' => 'sometimes',
            'attachments' => [],
            'limit' => [],
            'agent' => [],
        ];

        foreach ($invalidParameters as $name => $value) {
            $controller = $this->createController(
                $this->createStub(StatementRepositoryInterface::class),
                $this->createStub(StatementResultSerializerInterface::class),
                $this->createStub(StatementSerializerInterface::class),
                new StatementsFilterFactory($this->createStub(ActorSerializerInterface::class))
            );
            $request = new Request();
            $request->query->set($name, $value);

            try {
                $controller->getStatements($request);
                self::fail(sprintf('Expected invalid "%s" to be rejected.', $name));
            } catch (BadRequestException) {
                self::assertTrue(true);
            }
        }
    }

    public function testInvalidAgentObjectIsRejectedAsBadRequest(): void
    {
        $actorSerializer = $this->createStub(ActorSerializerInterface::class);
        $actorSerializer->method('deserializeActor')->willThrowException(new ActorDeserializationException('Invalid Agent.'));

        $controller = $this->createController(
            $this->createStub(StatementRepositoryInterface::class),
            $this->createStub(StatementResultSerializerInterface::class),
            $this->createStub(StatementSerializerInterface::class),
            new StatementsFilterFactory($actorSerializer)
        );
        $request = new Request();
        $request->query->set('agent', '{"mbox":"mailto:learner@example.com"}');

        $this->expectException(BadRequestException::class);
        $controller->getStatements($request);
    }

    public function testSinceExcludesTheBoundaryAndKeepsPaginationComplete(): void
    {
        $since = '2024-01-01T12:00:00.500Z';
        $statements = [
            StatementFixtures::getMinimalStatement('12345678-1234-5678-8234-567812345678')
                ->withStored(new \DateTime('2024-01-01T12:00:00.500Z')),
            StatementFixtures::getMinimalStatement('12345678-1234-5678-8234-567812345679')
                ->withStored(new \DateTime('2024-01-01T12:00:00.501Z')),
            StatementFixtures::getMinimalStatement('12345678-1234-5678-8234-567812345680')
                ->withStored(new \DateTime('2024-01-01T12:00:00.600Z')),
        ];

        $cache = new ArrayAdapter();
        $router = $this->createStub(Router::class);
        $router->method('generate')->willReturnCallback(static fn(string $route, array $parameters): string => '/statements?moreId='.$parameters['moreId']);

        $repository = $this->createMock(StatementRepositoryInterface::class);
        $repository->expects($this->exactly(3))
            ->method('findStatementsBy')
            ->willReturn($statements);

        $resultSerializer = $this->createStub(StatementResultSerializerInterface::class);
        $resultSerializer->method('serializeStatementResult')->willReturnCallback(
            static fn(StatementResult $result): string => json_encode([
                'statements' => array_map(
                    static fn($statement): string => $statement->getId()->getValue(),
                    $result->getStatements()
                ),
                'more' => $result->getMoreUrlPath()?->getValue(),
            ], JSON_THROW_ON_ERROR)
        );

        $controller = $this->createController(
            $repository,
            $resultSerializer,
            $this->createStub(StatementSerializerInterface::class),
            new StatementsFilterFactory($this->createStub(ActorSerializerInterface::class)),
            $cache,
            $router
        );

        $firstResponse = $controller->getStatements(Request::create('/statements?since='.rawurlencode($since).'&limit=1'));
        $firstResult = json_decode($firstResponse->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame([$statements[1]->getId()->getValue()], $firstResult['statements']);
        self::assertNotSame('', $firstResult['more']);

        $secondResponse = $controller->getStatements(Request::create($firstResult['more']));
        $secondResult = json_decode($secondResponse->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame([$statements[2]->getId()->getValue()], $secondResult['statements']);
        self::assertSame('', $secondResult['more']);
    }

    public function testStatementReferencePropagationIsDelegatedToRepository(): void
    {
        $target = StatementFixtures::getMinimalStatement('12345678-1234-5678-8234-567812345678')
            ->withStored(new \DateTime('2024-01-01T00:00:00Z'));
        $middle = StatementFixtures::getMinimalStatement('12345678-1234-5678-8234-567812345679')
            ->withObject(new StatementReference($target->getId()))
            ->withStored(new \DateTime('2024-01-02T00:00:00Z'));
        $outer = StatementFixtures::getMinimalStatement('12345678-1234-5678-8234-567812345680')
            ->withObject(new StatementReference($middle->getId()))
            ->withStored(new \DateTime('2024-01-03T00:00:00Z'));

        $repository = $this->createMock(StatementRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('findStatementsBy')
            ->willReturnCallback(static function ($filter) use ($outer, $middle, $target): array {
                $criteria = $filter->getFilter();
                self::assertSame('https://example.com/activity', $criteria['activity']);
                self::assertSame('2024-01-02T00:00:00+00:00', $criteria['since']);

                return [$outer, $middle, $target];
            });

        $resultSerializer = $this->createStub(StatementResultSerializerInterface::class);
        $resultSerializer->method('serializeStatementResult')->willReturnCallback(
            static fn(StatementResult $result): string => json_encode([
                'statements' => array_map(
                    static fn($statement): string => $statement->getId()->getValue(),
                    $result->getStatements()
                ),
                'more' => $result->getMoreUrlPath()?->getValue(),
            ], JSON_THROW_ON_ERROR)
        );

        $controller = $this->createController(
            $repository,
            $resultSerializer,
            $this->createStub(StatementSerializerInterface::class),
            new StatementsFilterFactory($this->createStub(ActorSerializerInterface::class))
        );

        $response = $controller->getStatements(Request::create(
            '/statements?activity=https%3A%2F%2Fexample.com%2Factivity&since=2024-01-02T00%3A00%3A00Z'
        ));
        $result = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame([$outer->getId()->getValue()], $result['statements']);
        self::assertSame('', $result['more']);
    }

    public function testCanonicalFormatUsesRepositoryDataAndNegotiatesEachLanguageMap(): void
    {
        $activityId = 'https://example.com/activity';
        $verbId = 'https://example.com/verbs/completed';
        $activity = new Activity(IRI::fromString($activityId));
        $verb = new Verb(IRI::fromString($verbId), LanguageMap::create([
            'en' => 'completed',
            'fr' => 'terminé',
        ]));
        $statement = StatementFixtures::getMinimalStatement();

        $activityRepository = $this->createMock(ActivityRepositoryInterface::class);
        $activityRepository->expects($this->once())
            ->method('findActivityById')
            ->with(self::callback(static fn(IRI $iri): bool => $iri->getValue() === $activityId))
            ->willReturn($activity);

        $verbRepository = $this->createMock(VerbRepositoryInterface::class);
        $verbRepository->expects($this->once())
            ->method('findVerbById')
            ->with(self::callback(static fn(IRI $iri): bool => $iri->getValue() === $verbId))
            ->willReturn($verb);

        $repository = $this->createStub(StatementRepositoryInterface::class);
        $repository->method('findStatementsBy')->willReturn([$statement]);

        $resultSerializer = $this->createStub(StatementResultSerializerInterface::class);
        $resultSerializer->method('serializeStatementResult')->willReturn(json_encode([
            'statements' => [[
                'actor' => ['objectType' => 'Agent', 'mbox' => 'mailto:original@example.com', 'name' => 'Original'],
                'verb' => ['id' => $verbId, 'display' => ['en' => 'old', 'fr' => 'ancien']],
                'object' => ['objectType' => 'Activity', 'id' => $activityId, 'definition' => [
                    'name' => ['en' => 'old', 'fr' => 'ancien'],
                ]],
                'context' => ['contextActivities' => [
                    'parent' => [[
                        'objectType' => 'Activity',
                        'id' => $activityId,
                        'definition' => ['description' => ['en' => 'old', 'fr' => 'ancien']],
                    ]],
                ]],
            ]],
            'more' => '',
        ], JSON_THROW_ON_ERROR));

        $activitySerializer = $this->createStub(ActivitySerializerInterface::class);
        $activitySerializer->method('serializeActivity')->willReturn(json_encode([
            'objectType' => 'Activity',
            'id' => $activityId,
            'definition' => [
                'name' => ['en' => 'Canonical name', 'fr' => 'Nom canonique'],
                'description' => ['en' => 'Canonical description', 'fr' => 'Description canonique'],
                'choices' => [
                    ['id' => 'one', 'description' => ['en' => 'One', 'fr' => 'Un']],
                ],
            ],
        ], JSON_THROW_ON_ERROR));

        $controller = $this->createController(
            $repository,
            $resultSerializer,
            $this->createStub(StatementSerializerInterface::class),
            new StatementsFilterFactory($this->createStub(ActorSerializerInterface::class)),
            activityRepository: $activityRepository,
            verbRepository: $verbRepository,
            activitySerializer: $activitySerializer
        );
        $request = Request::create('/statements?format=canonical', Request::METHOD_GET, [], [], [], [
            'HTTP_ACCEPT_LANGUAGE' => 'fr-CA,fr;q=0.9,en;q=0.8',
        ]);

        $response = $controller->getStatements($request);
        $result = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $canonicalStatement = $result['statements'][0];

        self::assertSame([
            'objectType' => 'Agent',
            'mbox' => 'mailto:original@example.com',
            'name' => 'Original',
        ], $canonicalStatement['actor']);
        self::assertSame(['id' => $verbId, 'display' => ['fr' => 'terminé']], $canonicalStatement['verb']);
        self::assertSame(['fr' => 'Nom canonique'], $canonicalStatement['object']['definition']['name']);
        self::assertSame(['fr' => 'Description canonique'], $canonicalStatement['object']['definition']['description']);
        self::assertSame(['fr' => 'Un'], $canonicalStatement['object']['definition']['choices'][0]['description']);
        self::assertSame(['fr' => 'Description canonique'], $canonicalStatement['context']['contextActivities']['parent'][0]['definition']['description']);
    }

    public function testMalformedSinceOrUntilTimestampIsRejected(): void
    {
        $controller = $this->createController(
            $this->createStub(StatementRepositoryInterface::class),
            $this->createStub(StatementResultSerializerInterface::class),
            $this->createStub(StatementSerializerInterface::class),
            new StatementsFilterFactory($this->createStub(ActorSerializerInterface::class))
        );

        foreach (['since', 'until'] as $parameter) {
            foreach (['2024-02-30T12:00:00Z', '2024-01-01T12:60:00Z'] as $timestamp) {
                try {
                    $controller->getStatements(Request::create('/statements?'.$parameter.'='.rawurlencode($timestamp)));
                    self::fail(sprintf('Expected malformed %s timestamp to be rejected.', $parameter));
                } catch (BadRequestException) {
                    self::assertTrue(true);
                }
            }
        }
    }

    public function testIdsFormatStripsPropertiesBeyondObjectIdentifiers(): void
    {
        $statement = StatementFixtures::getMinimalStatement();
        $repository = $this->createStub(StatementRepositoryInterface::class);
        $repository->method('findStatementsBy')->willReturn([$statement]);

        $serializedResult = [
            'statements' => [[
                'actor' => ['objectType' => 'Agent', 'name' => 'Learner', 'mbox' => 'mailto:learner@example.com'],
                'verb' => ['id' => 'https://example.com/verbs/completed', 'display' => ['en' => 'completed']],
                'object' => ['objectType' => 'Activity', 'id' => 'https://example.com/activity', 'definition' => ['name' => ['en' => 'Activity']]],
                'context' => [
                    'instructor' => ['objectType' => 'Agent', 'name' => 'Instructor', 'mbox' => 'mailto:instructor@example.com'],
                    'contextActivities' => ['parent' => [['objectType' => 'Activity', 'id' => 'https://example.com/parent', 'definition' => ['name' => ['en' => 'Parent']]]]],
                ],
            ]],
            'more' => '',
        ];

        $resultSerializer = $this->createStub(StatementResultSerializerInterface::class);
        $resultSerializer->method('serializeStatementResult')->willReturn(json_encode($serializedResult, JSON_THROW_ON_ERROR));

        $controller = $this->createController(
            $repository,
            $resultSerializer,
            $this->createStub(StatementSerializerInterface::class),
            new StatementsFilterFactory($this->createStub(ActorSerializerInterface::class))
        );

        $response = $controller->getStatements(Request::create('/statements?format=ids&limit=1'));
        $actual = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame([
            'mbox' => 'mailto:learner@example.com',
            'objectType' => 'Agent',
        ], $actual['statements'][0]['actor']);
        self::assertSame(['id' => 'https://example.com/verbs/completed'], $actual['statements'][0]['verb']);
        self::assertSame(['objectType' => 'Activity', 'id' => 'https://example.com/activity'], $actual['statements'][0]['object']);
        self::assertArrayNotHasKey('name', $actual['statements'][0]['context']['instructor']);
        self::assertArrayNotHasKey('definition', $actual['statements'][0]['context']['contextActivities']['parent'][0]);
    }

    public function testUnknownQueryParameterIsRejected(): void
    {
        $controller = $this->createController(
            $this->createStub(StatementRepositoryInterface::class),
            $this->createStub(StatementResultSerializerInterface::class),
            $this->createStub(StatementSerializerInterface::class),
            new StatementsFilterFactory($this->createStub(ActorSerializerInterface::class))
        );

        $this->expectException(BadRequestException::class);
        $controller->getStatements(Request::create('/statements?unknown=value'));
    }

    public function testMoreLinkHidesAndRestoresNextPageParameters(): void
    {
        $cache = new ArrayAdapter();
        $router = $this->createStub(Router::class);
        $router->method('generate')->willReturnCallback(static function (string $route, array $parameters): string {
            self::assertSame('xapi_lrs.statement.get', $route);
            self::assertCount(1, $parameters);
            self::assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/', $parameters['moreId']);

            return '/statements?'.http_build_query($parameters);
        });

        $statements = [
            StatementFixtures::getMinimalStatement('12345678-1234-5678-8234-567812345678'),
            StatementFixtures::getMinimalStatement('12345678-1234-5678-8234-567812345679'),
        ];

        $repository = $this->createMock(StatementRepositoryInterface::class);
        $repository->expects($this->exactly(2))
            ->method('findStatementsBy')
            ->willReturnCallback(static function ($filter) use ($statements): array {
                if (isset($filter->getFilter()['activity'])) {
                    self::assertSame('http://example.com/activity', $filter->getFilter()['activity']);
                }

                return $statements;
            });

        $factory = new StatementsFilterFactory($this->createStub(ActorSerializerInterface::class));
        $resultSerializer = $this->createStub(StatementResultSerializerInterface::class);
        $resultSerializer->method('serializeStatementResult')->willReturnCallback(
            static fn(StatementResult $result): string => json_encode([
                'statements' => array_map(
                    static fn($statement): string => $statement->getId()->getValue(),
                    $result->getStatements()
                ),
                'more' => $result->getMoreUrlPath()?->getValue(),
            ], JSON_THROW_ON_ERROR)
        );

        $controller = $this->createController(
            $repository,
            $resultSerializer,
            $this->createStub(StatementSerializerInterface::class),
            $factory,
            $cache,
            $router
        );

        $firstResponse = $controller->getStatements(Request::create(
            '/statements?limit=1&activity=http%3A%2F%2Fexample.com%2Factivity'
        ));
        $firstResult = json_decode($firstResponse->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame([$statements[0]->getId()->getValue()], $firstResult['statements']);
        self::assertMatchesRegularExpression('/\A\/statements\?moreId=[a-f0-9]{64}\z/', $firstResult['more']);

        $continuationRequest = Request::create($firstResult['more']);
        $secondResponse = $controller->getStatements($continuationRequest);
        $secondResult = json_decode($secondResponse->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame([$statements[1]->getId()->getValue()], $secondResult['statements']);
        self::assertSame('', $secondResult['more']);
    }

    public function testExpiredMoreLinkReturnsNotFound(): void
    {
        $controller = $this->createController(
            $this->createStub(StatementRepositoryInterface::class),
            $this->createStub(StatementResultSerializerInterface::class),
            $this->createStub(StatementSerializerInterface::class),
            new StatementsFilterFactory($this->createStub(ActorSerializerInterface::class))
        );

        $this->expectException(NotFoundException::class);

        $controller->getStatements(Request::create('/statements?moreId='.str_repeat('a', 64)));
    }

    private function createController(
        StatementRepositoryInterface $repository,
        StatementResultSerializerInterface $resultSerializer,
        StatementSerializerInterface $statementSerializer,
        StatementsFilterFactory $filterFactory,
        ?CacheInterface $cache = null,
        ?Router $router = null,
        ?ActivityRepositoryInterface $activityRepository = null,
        ?VerbRepositoryInterface $verbRepository = null,
        ?ActivitySerializerInterface $activitySerializer = null
    ): StatementGetController {
        return new StatementGetController(
            $repository,
            $resultSerializer,
            $statementSerializer,
            new StatementFormatNormalizer(
                $activityRepository ?? $this->createStub(ActivityRepositoryInterface::class),
                $verbRepository ?? $this->createStub(VerbRepositoryInterface::class),
                $activitySerializer ?? $this->createStub(ActivitySerializerInterface::class)
            ),
            new StatementGetQueryValidator($filterFactory),
            new StatementContinuationManager(
                $cache ?? new ArrayAdapter(),
                $router ?? $this->createStub(Router::class)
            )
        );
    }
}
