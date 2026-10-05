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

use Exception;
use Symfony\Component\HttpFoundation\Response;
use Xabbuh\XApi\Common\Exception\NotFoundException;
use Xabbuh\XApi\Model\Statement;
use Xabbuh\XApi\Model\StatementId;
use XApi\LrsBundle\Exception\BadRequestHttpException;
use XApi\LrsBundle\Exception\ConflictHttpException;
use XApi\LrsBundle\Response\JsonXapiResponse;
use XApi\Repository\Api\StatementRepositoryInterface;

/**
 * @author Jérôme Parmentier <jerome.parmentier@acensi.fr>
 * @author Mathieu Boldo <mathieu.boldo@entrili.com>
 */
final readonly class StatementPostController
{
    public function __construct(private StatementRepositoryInterface $statementRepository) { }

    public function postStatements(Statement $statement): JsonXapiResponse
    {
        $statement = $this->resolveStatement($statement);

        $this->storeStatement($statement);

        return new JsonXapiResponse([$statement->getId()?->getValue()], Response::HTTP_OK);
    }

    /**
     * @param Statement[] $statements
     */
    public function postStatementss(array $statements): JsonXapiResponse
    {
        $uuids = [];

        foreach ($statements as $statement) {

            try {
                $statement = $this->resolveStatement($statement);
                $this->storeStatement($statement);
                $uuids[] = $statement->getId()?->getValue();
            } catch (Exception) {
                // Ignore...
            }
        }

        return new JsonXapiResponse($uuids, Response::HTTP_OK);
    }

    private function resolveStatement(Statement $statement): Statement
    {
        if (!$statement->getId() instanceof StatementId) {
            throw new BadRequestHttpException(sprintf('Parameter statementId ("%s") is not a valid UUID.', $statement->getId()?->getValue()));
        }

        return $statement;
    }

    private function storeStatement(Statement $statement): void
    {
        try {
            $existingStatement = $this->statementRepository->findStatementById($statement->getId());

            if (!$existingStatement->equals($statement)) {
                throw new ConflictHttpException('The new statement is not equal to an existing statement with the same id.');
            }
        } catch (NotFoundException) {
            $this->statementRepository->storeStatement($statement);
        }
    }
}
