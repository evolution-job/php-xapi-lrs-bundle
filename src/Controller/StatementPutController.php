<?php

/*
 * This file is part of the xAPI package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace XApi\LrsBundle\Controller;

use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Xabbuh\XApi\Common\Exception\BadRequestException;
use Xabbuh\XApi\Common\Exception\ConflictException;
use Xabbuh\XApi\Common\Exception\NotFoundException;
use Xabbuh\XApi\Model\Statement;
use Xabbuh\XApi\Model\StatementId;
use XApi\LrsBundle\Response\JsonResponse;
use XApi\Repository\Api\StatementRepositoryInterface;

/**
 * @author Christian Flothmann <christian.flothmann@xabbuh.de>
 * @author Jérôme Parmentier <jerome.parmentier@acensi.fr>
 * @author Mathieu Boldo <mathieu.boldo@entrili.com>
 */
final readonly class StatementPutController
{
    public function __construct(private StatementRepositoryInterface $statementRepository) { }

    public function putStatements(Request $request, Statement $statement): JsonResponse
    {
        if (null === $id = $request->query->all()['statementId'] ?? null) {
            throw new BadRequestException('Required statementId parameter is missing.');
        }

        if (!is_string($id)) {
            throw new BadRequestException('Required statementId parameter is not a string.');
        }

        $statement = $this->resolveStatement($id, $statement);

        try {
            $existingStatement = $this->statementRepository->findStatementById($statement->getId());

            if (!$existingStatement->equals($statement)) {
                throw new ConflictException('The new statement is not equal to an existing statement with the same id.');
            }
        } catch (NotFoundException) {
            $this->statementRepository->storeStatement($statement);
        }

        return new JsonResponse(status: Response::HTTP_NO_CONTENT);
    }

    private function resolveStatement(string $id, Statement $statement): Statement
    {
        try {
            $statementId = StatementId::fromString($id);

            if (!$statement->getId() instanceof StatementId) {
                $statement = $statement->withId($statementId);
            }

            if (!$statement->getId() instanceof StatementId) {
                throw new InvalidArgumentException('');
            }

        } catch (InvalidArgumentException) {
            throw new BadRequestException(sprintf('Parameter statementId ("%s") is not a valid UUID.', $id));
        }

        if (!$statementId->equals($statement->getId())) {
            throw new ConflictException(sprintf('Id parameter ("%s") and statement id ("%s") do not match.', $id, $statement->getId()->getValue()));
        }

        return $statement;
    }
}
