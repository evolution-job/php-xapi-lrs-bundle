<?php

/*
 * This file is part of the xAPI package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace XApi\LrsBundle\Controller;

use DateMalformedStringException;
use DateTime;
use DateTimeImmutable;
use JsonException;
use Psr\Cache\InvalidArgumentException;
use Random\RandomException;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Xabbuh\XApi\Common\Exception\BadRequestException;
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
final readonly class StatementGetController
{
    private const int SERVER_LIMIT = 100;

    public function __construct(
        private StatementRepositoryInterface $statementRepository,
        private StatementResultSerializerInterface $statementResultSerializer,
        private StatementSerializerInterface $statementSerializer,
        private StatementFormatNormalizer $statementFormatNormalizer,
        private StatementGetQueryValidator $statementGetQueryValidator,
        private StatementContinuationManager $statementContinuationManager
    ) { }

    /**
     * @throws DateMalformedStringException
     * @throws InvalidArgumentException
     * @throws JsonException
     * @throws UnsupportedStatementVersionException
     * @throws RandomException
     */
    public function getStatements(Request $request): JsonResponse|MultipartResponse
    {
        $parameters = $this->statementContinuationManager->resolveParameters($request);
        $parameterBag = new ParameterBag($parameters);
        $this->statementGetQueryValidator->validate($parameterBag);

        $format = $parameterBag->get('format', 'exact');
        $includeAttachments = $parameterBag->filter('attachments', false, FILTER_VALIDATE_BOOLEAN);
        $limit = $this->getPageSize($parameterBag);
        $offset = $parameterBag->getInt('offset');
        $since = $parameterBag->has('since') ? $this->statementGetQueryValidator->parseTimestamp($parameterBag->get('since'), 'since') : null;
        $statementId = $parameterBag->get('statementId');
        $until = $parameterBag->has('until') ? $this->statementGetQueryValidator->parseTimestamp($parameterBag->get('until'), 'until') : null;

        if (null !== $statementId) {
            try {
                $statement = $this->statementRepository->findStatementById(StatementId::fromString($statementId));
            } catch (NotFoundException) {
                throw new NotFoundException('The requested statement was not found.');
            }

            return $this->buildSingleStatementResponse($request, $statement, $includeAttachments, $format);
        }

        $voidedStatementId = $parameterBag->get('voidedStatementId');
        if (null !== $voidedStatementId) {
            try {
                $statement = $this->statementRepository->findVoidedStatementById(StatementId::fromString($voidedStatementId));
            } catch (NotFoundException) {
                throw new NotFoundException('The requested voided statement was not found.');
            }

            return $this->buildSingleStatementResponse($request, $statement, $includeAttachments, $format);
        }

        // Timestamp filtering happens after the repository query. Fetch additional
        // candidates as needed so filtered-out boundary rows do not truncate a page.
        $requiredCount = $offset + $limit + 1;
        $fetchLimit = $requiredCount;
        do {
            try {
                $statementsFilter = $this->statementGetQueryValidator->createStatementsFilter($parameterBag, $fetchLimit, $until);
            } catch (ActorDeserializationException) {
                throw new BadRequestException('The agent parameter must be a valid xAPI Agent or Group object.');
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

        // xAPI clients follow the returned "more" URL verbatim to retrieve the
        // next page; the continuation manager keeps its query state behind a token.
        // https://github.com/adlnet/xAPI-Spec/blob/master/xAPI-Communication.md
        $more = $hasMore ? $this->statementContinuationManager->createMoreUrl($parameterBag->all(), $limit, $offset) : null;

        return $this->buildMultiStatementsResponse($request, $statements, $includeAttachments, $more, $format);
    }

    /**
     * @param Statement[] $statements
     */
    protected function buildMultipartResponse(JsonResponse $jsonResponse, array $statements): MultipartResponse
    {
        $attachmentsParts = [];

        foreach ($statements as $statement) {
            foreach ((array)$statement->getAttachments() as $attachment) {
                $attachmentsParts[] = new AttachmentResponse($attachment);
            }
        }

        return new MultipartResponse($jsonResponse, $attachmentsParts);
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
        ?IRL $irl = null,
        string $format = 'exact'
    ): JsonResponse|MultipartResponse {

        $statementResult = new StatementResult($statements, $irl ?? IRL::fromString(''));
        $json = $this->statementResultSerializer->serializeStatementResult($statementResult);
        $json = $this->statementFormatNormalizer->normalize($json, $format, $request, true);

        $jsonResponse = new JsonResponse($json, Response::HTTP_OK, json: true, isHeadRequest: $request->isMethod(Request::METHOD_HEAD));

        if ($includeAttachments) {
            return $this->buildMultipartResponse($jsonResponse, $statements);
        }

        return $jsonResponse;
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
        $json = $this->statementFormatNormalizer->normalize($json, $format, $request);

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
        if (!$since instanceof DateTimeImmutable && !$until instanceof DateTimeImmutable) {
            return $statements;
        }

        // Statement retrieval uses an exclusive "since" and inclusive "until"
        // boundary. Apply them to stored timestamps here, including sub-second precision.
        // https://github.com/adlnet/xAPI-Spec/blob/master/xAPI-Communication.md
        return array_values(
            array_filter(
                $statements,
                static function (Statement $statement) use ($since, $until): bool {
                    if (!$statement->getStored() instanceof DateTime) {
                        return false;
                    }

                    $stored = DateTimeImmutable::createFromMutable($statement->getStored());

                    return (!$since instanceof DateTimeImmutable || $stored > $since)
                        && (!$until instanceof DateTimeImmutable || $stored <= $until);
                }
            )
        );
    }

    private function getPageSize(ParameterBag $parameterBag): int
    {
        $limit = $parameterBag->getInt('limit');

        return 0 === $limit || self::SERVER_LIMIT < $limit ? self::SERVER_LIMIT : $limit;
    }
}
