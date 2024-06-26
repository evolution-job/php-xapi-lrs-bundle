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

use InvalidArgumentException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Xabbuh\XApi\Common\Exception\NotFoundException;
use Xabbuh\XApi\Model\Statement;
use Xabbuh\XApi\Model\StatementId;
use XApi\Repository\Api\StatementRepositoryInterface;

/**
 * @author Jérôme Parmentier <jerome.parmentier@acensi.fr>
 * @author Mathieu Boldo <mathieu.boldo@entrili.com>
 */
final class StatementPostController
{
    public function __construct(private readonly StatementRepositoryInterface $statementRepository) {}

    public function postStatement(Request $request, Statement $statement): JsonResponse
    {
        if (null === $request->query->get('statementId')) {
            throw new BadRequestHttpException('Required statementId parameter is missing.');
        }

        $this->storeStatement($statement);

        return new JsonResponse($statement->getId(), 200);
    }

    public function postStatements(array $statements): JsonResponse
    {
        $uuids = [];

        foreach ($statements as $statement) {

            $this->storeStatement($statement);

            $uuids[] = $statement->getId();
        }

        return new JsonResponse($uuids, 200);
    }

    private function storeStatement(Statement $statement): void
    {
        try {
            $id = StatementId::fromString($statement->getId());
        } catch (InvalidArgumentException $invalidArgumentException) {
            throw new BadRequestHttpException(sprintf('Parameter statementId ("%s") is not a valid UUID.', $statement->getId()?->getValue()), $invalidArgumentException);
        }

        if ($statement->getId() instanceof StatementId && !$id->equals($statement->getId())) {
            throw new ConflictHttpException(sprintf('Id parameter ("%s") and statement id ("%s") do not match.', $id->getValue(), $statement->getId()->getValue()));
        }

        if (!$statement->getId() instanceof StatementId) {
            $statement = $statement->withId($id);
        }

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
