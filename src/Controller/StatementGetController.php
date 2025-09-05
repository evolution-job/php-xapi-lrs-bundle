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
use Symfony\Component\HttpFoundation\InputBag;
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
    private static array $notAllowed = [
        'activity'           => true,
        'agent'              => true,
        'ascending'          => true,
        'limit'              => true,
        'registration'       => true,
        'related_activities' => true,
        'related_agents'     => true,
        'since'              => true,
        'until'              => true,
        'verb'               => true,
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
        $query = $request->query;

        $this->validate($query);

        $includeAttachments = $query->filter('attachments', false, FILTER_VALIDATE_BOOLEAN);

        try {
            if (($statementId = $query->get('statementId')) !== null) {
                // Unique
                $statement = $this->statementRepository->findStatementById(StatementId::fromString($statementId));
                $response = $this->buildSingleStatementResponse($statement, $includeAttachments);
            } elseif (($voidedStatementId = $query->get('voidedStatementId')) !== null) {
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
     * Validate the parameters.
     *
     * @throws BadRequestHttpException if the parameters does not comply with the xAPI specification
     */
    private function validate(ParameterBag $parameterBag): void
    {
        $hasStatementId = $parameterBag->has('statementId');
        $hasVoidedStatementId = $parameterBag->has('voidedStatementId');

        if ($hasStatementId && $hasVoidedStatementId) {
            throw new BadRequestHttpException('Request must not have both statementId and voidedStatementId parameters at the same time.');
        }

        if ($hasStatementId || $hasVoidedStatementId) {
            $badKeys = array_intersect_key($parameterBag->all(), self::$notAllowed);

            if ([] !== $badKeys) {
                throw new BadRequestHttpException(sprintf('Cannot have "%s" parameters. Only "format" and/or "attachments" are allowed with "statementId" or "voidedStatementId".', implode('", "', array_keys($badKeys))));
            }
        }
    }

    private function createStatementsFilters(InputBag $query): StatementsFilter
    {
        $limit = $query->getInt('limit');

        if ($limit === 0 || $limit > 1000) {
            $query->set('limit', 1000); // Server limit
        }

        return $this->statementsFilterFactory->createFromParameterBag($query);
    }
}
