<?php

/*
 * This file is part of the xAPI package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace XApi\LrsBundle\Controller;

use Symfony\Component\HttpFoundation\Response;
use Xabbuh\XApi\Common\Exception\BadRequestException;
use Xabbuh\XApi\Common\Exception\ConflictException;
use Xabbuh\XApi\Common\Exception\NotFoundException;
use Xabbuh\XApi\Model\Statement;
use Xabbuh\XApi\Model\StatementId;
use Xabbuh\XApi\Model\Uuid;
use XApi\LrsBundle\Response\JsonResponse;
use XApi\Repository\Api\StatementRepositoryInterface;

/**
 * @author Jérôme Parmentier <jerome.parmentier@acensi.fr>
 * @author Mathieu Boldo <mathieu.boldo@entrili.com>
 */
final readonly class StatementPostController
{
    public function __construct(private StatementRepositoryInterface $statementRepository) { }

    public function postStatement(Statement $statement): JsonResponse
    {
        $statement = $this->resolveStatement($statement);

        if ($this->shouldStoreStatement($statement)) {
            $this->statementRepository->storeStatement($statement);
        }

        return new JsonResponse([$statement->getId()?->getValue()], Response::HTTP_OK);
    }

    /**
     * @param Statement[] $statements
     */
    public function postStatements(array $statements): JsonResponse
    {
        $resolvedStatements = [];
        $statementIds = [];

        foreach ($statements as $statement) {
            $statement = $this->resolveStatement($statement);
            $statementId = $statement->getId()->getValue();

            if (isset($statementIds[$statementId])) {
                throw new BadRequestException(sprintf('The statement batch contains duplicate statement id "%s".', $statementId));
            }

            $statementIds[$statementId] = true;
            $resolvedStatements[] = $statement;
        }

        $statementsToStore = [];
        foreach ($resolvedStatements as $statement) {
            if ($this->shouldStoreStatement($statement)) {
                $statementsToStore[] = $statement;
            }
        }

        $lastIndex = count($statementsToStore) - 1;
        foreach ($statementsToStore as $index => $statement) {
            $this->statementRepository->storeStatement($statement, $index === $lastIndex);
        }

        $uuids = array_map(
            static fn (Statement $statement): string => $statement->getId()->getValue(),
            $resolvedStatements
        );

        return new JsonResponse($uuids, Response::HTTP_OK);
    }

    private function resolveStatement(Statement $statement): Statement
    {
        return $statement->getId() instanceof StatementId
            ? $statement
            : $statement->withId(StatementId::fromUuid(Uuid::uuid4()));
    }

    private function shouldStoreStatement(Statement $statement): bool
    {
        try {
            $existingStatement = $this->statementRepository->findStatementById($statement->getId());

            if (!$existingStatement->equals($statement)) {
                throw new ConflictException('The new statement is not equal to an existing statement with the same id.');
            }
        } catch (NotFoundException) {
            return true;
        }

        return false;
    }
}
