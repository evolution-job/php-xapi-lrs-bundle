<?php

/*
 * This file is part of the xAPI package.
 *
 * (c) Christian Flothmann <christian.flothmann@xabbuh.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace XApi\LrsBundle\Controller;

use DateMalformedStringException;
use DateTimeImmutable;
use JsonException;
use Psr\Cache\InvalidArgumentException;
use Random\RandomException;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Xabbuh\XApi\Common\Exception\NotFoundException;
use Xabbuh\XApi\Common\Exception\UnsupportedStatementVersionException;
use Xabbuh\XApi\Model\IRL;
use Xabbuh\XApi\Model\Statement;
use Xabbuh\XApi\Model\StatementId;
use Xabbuh\XApi\Model\StatementResult;
use Xabbuh\XApi\Serializer\Exception\ActorDeserializationException;
use Xabbuh\XApi\Serializer\StatementResultSerializerInterface;
use Xabbuh\XApi\Serializer\StatementSerializerInterface;
use XApi\LrsBundle\App\XapiVersion;
use XApi\LrsBundle\Exception\BadRequestHttpException;
use XApi\LrsBundle\Exception\NotFoundHttpException;
use XApi\LrsBundle\Response\AttachmentResponse;
use XApi\LrsBundle\Response\JsonResponse;
use XApi\LrsBundle\Response\MultipartResponse;
use XApi\LrsBundle\Service\StatementContinuationManager;
use XApi\LrsBundle\Service\StatementFormatNormalizer;
use XApi\LrsBundle\Service\StatementGetQueryValidator;
use XApi\Repository\Api\StatementRepositoryInterface;

/**
 * @author Jérôme Parmentier <jerome.parmentier@acensi.fr>
 * @author Mathieu Boldo <mathieu.boldo@entrili.com>
 */
final class StatementGetController
{
    private const int SERVER_LIMIT = 100;

    public function __construct(
        private readonly StatementRepositoryInterface $statementRepository,
        private readonly StatementResultSerializerInterface $statementResultSerializer,
        private readonly StatementSerializerInterface $statementSerializer,
        private readonly StatementFormatNormalizer $formatNormalizer,
        private readonly StatementGetQueryValidator $queryValidator,
        private readonly StatementContinuationManager $continuationManager
    ) { }

    /**
     * @param Request $request
     * @return JsonResponse|MultipartResponse
     * @throws DateMalformedStringException
     * @throws InvalidArgumentException
     * @throws JsonException
     * @throws UnsupportedStatementVersionException
     * @throws RandomException
     */
    public function getStatements(Request $request): JsonResponse|MultipartResponse
    {
        $parameters = $this->continuationManager->resolveParameters($request);
        $query = new ParameterBag($parameters);
        $this->queryValidator->validate($query);

        $format = $query->get('format', 'exact');
        $includeAttachments = $query->filter('attachments', false, FILTER_VALIDATE_BOOLEAN);
        $limit = $this->getPageSize($query);
        $offset = $query->getInt('offset');
        $since = $query->has('since') ? $this->queryValidator->parseTimestamp($query->get('since'), 'since') : null;
        $statementId = $query->get('statementId');
        $until = $query->has('until') ? $this->queryValidator->parseTimestamp($query->get('until'), 'until') : null;

        if (null !== $statementId) {
            try {
                $statement = $this->statementRepository->findStatementById(StatementId::fromString($statementId));
            } catch (NotFoundException $exception) {
                throw new NotFoundHttpException('The requested statement was not found.', $exception);
            }

            return $this->buildSingleStatementResponse($request, $statement, $includeAttachments, $format);
        }

        $voidedStatementId = $query->get('voidedStatementId');
        if (null !== $voidedStatementId) {
            try {
                $statement = $this->statementRepository->findVoidedStatementById(StatementId::fromString($voidedStatementId));
            } catch (NotFoundException $exception) {
                throw new NotFoundHttpException('The requested voided statement was not found.', $exception);
            }

            return $this->buildSingleStatementResponse($request, $statement, $includeAttachments, $format);
        }

        $requiredCount = $offset + $limit + 1;
        $fetchLimit = $requiredCount;
        do {
            try {
                $statementsFilter = $this->queryValidator->createStatementsFilter($query, $fetchLimit, $until);
            } catch (ActorDeserializationException $exception) {
                throw new BadRequestHttpException('The agent parameter must be a valid xAPI Agent or Group object.', $exception);
            }

            $statements = $this->statementRepository->findStatementsBy($statementsFilter);
            $resultCount = count($statements);
            $statements = $this->filterByStoredTime($statements, $since, $until);

            if ($resultCount < $fetchLimit || $requiredCount <= count($statements)) {
                break;
            }

            $fetchLimit = min(PHP_INT_MAX, $fetchLimit * 2);
        } while (true);

        $hasMore = count($statements) > $offset + $limit;
        $statements = array_slice($statements, $offset, $limit);

        $more = $hasMore ? $this->continuationManager->createMoreUrl($query->all(), $limit, $offset) : null;

        return $this->buildMultiStatementsResponse($request, $statements, $includeAttachments, $more, $format);
    }

    /**
     * @param Statement[] $statements
     */
    protected function buildMultipartResponse(JsonResponse $JsonXapiResponse, array $statements): MultipartResponse
    {
        $attachmentsParts = [];

        foreach ($statements as $statement) {
            foreach ((array)$statement->getAttachments() as $attachment) {
                $attachmentsParts[] = new AttachmentResponse($attachment);
            }
        }

        return new MultipartResponse($JsonXapiResponse, $attachmentsParts);
    }

    /**
     * @param Statement[] $statements
     * @param bool $includeAttachments true to include the attachments in the response, false otherwise
     * @throws JsonException
     */
    protected function buildMultiStatementsResponse(
        Request $request,
        array $statements,
        bool $includeAttachments = false,
        ?IRL $more = null,
        string $format = 'exact'
    ): JsonResponse|MultipartResponse {

        $statementResult = new StatementResult($statements, $more ?? IRL::fromString(''));
        $json = $this->statementResultSerializer->serializeStatementResult($statementResult);
        $json = $this->formatNormalizer->normalize($json, $format, $request, true);

        $JsonXapiResponse = new JsonResponse($json, Response::HTTP_OK, json: true, isHeadRequest: $request->isMethod(Request::METHOD_HEAD));

        if ($includeAttachments) {
            return $this->buildMultipartResponse($JsonXapiResponse, $statements);
        }

        return $JsonXapiResponse;
    }

    /**
     * @param bool $includeAttachments true to include the attachments in the response, false otherwise
     * @throws UnsupportedStatementVersionException|JsonException
     */
    protected function buildSingleStatementResponse(
        Request $request,
        Statement $statement,
        bool $includeAttachments = false,
        string $format = 'exact'
    ): JsonResponse|MultipartResponse {
        if (null === $statement->getVersion()) {
            $statement = $statement->withVersion(XapiVersion::V1_0_3);
        }

        $json = $this->statementSerializer->serializeStatement($statement);
        $json = $this->formatNormalizer->normalize($json, $format, $request);

        $response = new JsonResponse($json, Response::HTTP_OK, json: true, isHeadRequest: $request->isMethod(Request::METHOD_HEAD));

        if ($includeAttachments) {
            $response = $this->buildMultipartResponse($response, [$statement]);
        }

        $response->setLastModified($statement->getStored());

        return $response;
    }

    /**
     * @param Statement[] $statements
     * @return Statement[]
     */
    private function filterByStoredTime(array $statements, ?DateTimeImmutable $since, ?DateTimeImmutable $until): array
    {
        if (null === $since && null === $until) {
            return $statements;
        }

        return array_values(
            array_filter(
                $statements,
                static function (Statement $statement) use ($since, $until): bool {
                    if (null === $statement->getStored()) {
                        return false;
                    }

                    $stored = DateTimeImmutable::createFromMutable($statement->getStored());

                    return (null === $since || $stored > $since)
                        && (null === $until || $stored <= $until);
                }
            )
        );
    }

    private function getPageSize(ParameterBag $query): int
    {
        $limit = $query->getInt('limit');

        return 0 === $limit || self::SERVER_LIMIT < $limit ? self::SERVER_LIMIT : $limit;
    }
}
