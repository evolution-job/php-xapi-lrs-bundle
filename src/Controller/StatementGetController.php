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

use DateTime;
use DateTimeInterface;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Xabbuh\XApi\Common\Exception\NotFoundException;
use Xabbuh\XApi\Common\Exception\UnsupportedStatementVersionException;
use Xabbuh\XApi\Model\Statement;
use Xabbuh\XApi\Model\StatementId;
use Xabbuh\XApi\Model\StatementResult;
use Xabbuh\XApi\Model\StatementsFilter;
use Xabbuh\XApi\Serializer\StatementResultSerializerInterface;
use Xabbuh\XApi\Serializer\StatementSerializerInterface;
use XApi\LrsBundle\EventListener\VersionListener;
use XApi\LrsBundle\Model\StatementsFilterFactory;
use XApi\LrsBundle\Response\AttachmentResponse;
use XApi\LrsBundle\Response\MultipartResponse;
use XApi\LrsBundle\Response\XapiJsonResponse;
use XApi\Repository\Api\StatementRepositoryInterface;

/**
 * @author Jérôme Parmentier <jerome.parmentier@acensi.fr>
 */
final class StatementGetController
{
    private const int SERVER_LIMIT = 1000;

    protected static array $getParameters = [
        'activity'           => true,
        'agent'              => true,
        'ascending'          => true,
        'attachments'       => true,
        'format'            => true,
        'limit'              => true,
        'registration'       => true,
        'related_activities' => true,
        'related_agents'     => true,
        'since'              => true,
        'statementId'       => true,
        'until'              => true,
        'verb'               => true,
        'voidedStatementId' => true,
    ];

    public function __construct(
        private readonly StatementRepositoryInterface $statementRepository,
        private readonly StatementSerializerInterface $statementSerializer,
        private readonly StatementResultSerializerInterface $statementResultSerializer,
        private readonly StatementsFilterFactory $statementsFilterFactory
    ) {}

    /**
     * @throws BadRequestHttpException if the query parameters does not comply with xAPI specification
     */
    public function getStatement(Request $request): Response
    {
        $query = new ParameterBag(array_intersect_key($request->query->all(), self::$getParameters));

        $this->validate($query);

        $includeAttachments = $query->filter('attachments', false, FILTER_VALIDATE_BOOLEAN);

        try {
            if (null !== ($statementId = $query->get('statementId'))) {
                // Unique
                $statement = $this->statementRepository->findStatementById(StatementId::fromString($statementId));
                $response = $this->buildSingleStatementResponse($statement, $includeAttachments);
            } elseif (null !== ($voidedStatementId = $query->get('voidedStatementId'))) {
                // Voided
                $statement = $this->statementRepository->findVoidedStatementById(StatementId::fromString($voidedStatementId));
                $response = $this->buildSingleStatementResponse($statement, $includeAttachments);

            } else {
                // Multiple
                $statements = $this->statementRepository->findStatementsBy($this->createStatementsFilters($query));
                $response = $this->buildMultiStatementsResponse($statements, $includeAttachments);
            }
        } catch (NotFoundException|UnsupportedStatementVersionException) {
            $response = $this->buildMultiStatementsResponse([]);
        }

        $dateTime = new DateTime();
        $response->headers->set('X-Experience-API-Consistent-Through', $dateTime->format(DateTimeInterface::ATOM));

        return $response;
    }

    /**
     * @param Statement[] $statements
     */
    protected function buildMultipartResponse(XapiJsonResponse $xApiJsonResponse, array $statements): MultipartResponse
    {
        $attachmentsParts = [];

        foreach ($statements as $statement) {
            foreach ((array)$statement->getAttachments() as $attachment) {
                $attachmentsParts[] = new AttachmentResponse($attachment);
            }
        }

        return new MultipartResponse($xApiJsonResponse, $attachmentsParts);
    }

    /**
     * @param Statement[] $statements
     * @param bool $includeAttachments true to include the attachments in the response, false otherwise
     */
    protected function buildMultiStatementsResponse(array $statements, bool $includeAttachments = false): XapiJsonResponse|MultipartResponse
    {
        $json = $this->statementResultSerializer->serializeStatementResult(new StatementResult($statements));

        $xApiJsonResponse = new XapiJsonResponse($json, 200, [], true);

        if ($includeAttachments) {
            return $this->buildMultipartResponse($xApiJsonResponse, $statements);
        }

        return $xApiJsonResponse;
    }

    /**
     * @param bool $includeAttachments true to include the attachments in the response, false otherwise
     * @throws UnsupportedStatementVersionException
     */
    protected function buildSingleStatementResponse(Statement $statement, bool $includeAttachments = false): XapiJsonResponse|MultipartResponse
    {
        if (null === $statement->getVersion()) {
            $statement = $statement->withVersion(VersionListener::XAPI_VERSION_1_0_0);
        }

        $json = $this->statementSerializer->serializeStatement($statement);

        $response = new XapiJsonResponse($json, 200, [], true);

        if ($includeAttachments) {
            $response = $this->buildMultipartResponse($response, [$statement]);
        }

        $response->setLastModified($statement->getStored());

        return $response;
    }

    protected function createStatementsFilters(ParameterBag $query): StatementsFilter
    {
        $limit = $query->getInt('limit');

        if (0 === $limit || self::SERVER_LIMIT < $limit) {
            $query->set('limit', self::SERVER_LIMIT);
        }

        return $this->statementsFilterFactory->createFromParameterBag($query);
    }

    /**
     * Validate the Query parameters.
     *
     * @throws BadRequestHttpException if the parameters does not comply with the xAPI specification
     */
    protected function validate(ParameterBag $query): void
    {
        $hasStatementId = $query->has('statementId');
        $hasVoidedStatementId = $query->has('voidedStatementId');

        if ($hasStatementId && $hasVoidedStatementId) {
            throw new BadRequestHttpException('Request must not have both statementId and voidedStatementId parameters at the same time.');
        }

        $queryParameters = $query->all();
        unset(
            $queryParameters['attachments'],
            $queryParameters['format'],
            $queryParameters['statementId'],
            $queryParameters['voidedStatementId'],
        );

        if (($hasStatementId || $hasVoidedStatementId) && count($queryParameters)) {

            $badParameters = implode('", "', array_keys($queryParameters));

            throw new BadRequestHttpException(sprintf('Request must not contain statementId or voidedStatementId parameters, and also any other parameter like "%s" besides "attachments" or "format".', $badParameters));
        }
    }
}
