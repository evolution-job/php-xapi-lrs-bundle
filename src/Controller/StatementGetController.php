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
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Xabbuh\XApi\Common\Exception\NotFoundException;
use Xabbuh\XApi\Common\Exception\UnsupportedStatementVersionException;
use Xabbuh\XApi\Model\Statement;
use Xabbuh\XApi\Model\StatementId;
use Xabbuh\XApi\Model\StatementResult;
use Xabbuh\XApi\Serializer\StatementResultSerializerInterface;
use Xabbuh\XApi\Serializer\StatementSerializerInterface;
use XApi\LrsBundle\Model\StatementsFilterFactory;
use XApi\LrsBundle\Response\AttachmentResponse;
use XApi\LrsBundle\Response\MultipartResponse;
use XApi\Repository\Api\StatementRepositoryInterface;

/**
 * @author Jérôme Parmentier <jerome.parmentier@acensi.fr>
 */
final class StatementGetController
{
    private static array $notAllowed = [
        'agent'              => true,
        'verb'               => true,
        'activity'           => true,
        'registration'       => true,
        'related_activities' => true,
        'related_agents'     => true,
        'since'              => true,
        'until'              => true,
        'limit'              => true,
        'ascending'          => true,
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
                $statement = $this->statementRepository->findStatementById(StatementId::fromString($statementId));

                $response = $this->buildSingleStatementResponse($statement, $includeAttachments);
            } elseif (($voidedStatementId = $query->get('voidedStatementId')) !== null) {
                $statement = $this->statementRepository->findVoidedStatementById(StatementId::fromString($voidedStatementId));

                $response = $this->buildSingleStatementResponse($statement, $includeAttachments);
            } else {
                $statements = $this->statementRepository->findStatementsBy($this->statementsFilterFactory->createFromParameterBag($query));

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
     * @param Statement $statement
     * @param bool $includeAttachments true to include the attachments in the response, false otherwise
     * @return JsonResponse|MultipartResponse
     * @throws UnsupportedStatementVersionException
     */
    protected function buildSingleStatementResponse(Statement $statement, bool $includeAttachments = false): JsonResponse|MultipartResponse
    {
        if (null === $statement->getVersion()) {
            $statement = $statement->withVersion('1.0.0');
        }

        $json = $this->statementSerializer->serializeStatement($statement);

        $response = new JsonResponse($json, 200, [], true);

        if ($includeAttachments) {
            $response = $this->buildMultipartResponse($response, [$statement]);
        }

        $response->setLastModified($statement->getStored());

        return $response;
    }

    /**
     * @param Statement[] $statements
     * @param bool $includeAttachments true to include the attachments in the response, false otherwise
     * @return JsonResponse|MultipartResponse
     */
    protected function buildMultiStatementsResponse(array $statements, bool $includeAttachments = false): JsonResponse|MultipartResponse
    {
        $json = $this->statementResultSerializer->serializeStatementResult(new StatementResult($statements));

        $response = new JsonResponse($json, 200, [], true);

        if ($includeAttachments) {
            $response = $this->buildMultipartResponse($response, $statements);
        }

        return $response;
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
     * Validate the parameters.
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
}
