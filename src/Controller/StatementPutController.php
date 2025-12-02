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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Xabbuh\XApi\Common\Exception\NotFoundException;
use Xabbuh\XApi\Model\Statement;
use Xabbuh\XApi\Model\StatementId;
use XApi\LrsBundle\Response\XapiJsonResponse;
use XApi\Repository\Api\StatementRepositoryInterface;

/**
 * @author Christian Flothmann <christian.flothmann@xabbuh.de>
 * @author Jérôme Parmentier <jerome.parmentier@acensi.fr>
 */
final readonly class StatementPutController
{
    public function __construct(private StatementRepositoryInterface $statementRepository) { }

    public function putStatement(Request $request, Statement $statement): XapiJsonResponse
    {
        if (null === $id = $request->query->all()['statementId'] ?? null) {
            throw new BadRequestHttpException('Required statementId parameter is missing.');
        }

        if (!is_string($id)) {
            throw new BadRequestHttpException('Required statementId parameter is not a string.');
        }

        $statement = $this->resolveStatement($id, $statement);

        try {
            $existingStatement = $this->statementRepository->findStatementById($statement->getId());

            if (!$existingStatement->equals($statement)) {
                throw new ConflictHttpException('The new statement is not equal to an existing statement with the same id.');
            }
        } catch (NotFoundException) {
            $this->statementRepository->storeStatement($statement);
        }

        return new XapiJsonResponse('', Response::HTTP_OK);
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

        } catch (InvalidArgumentException $invalidArgumentException) {
            throw new BadRequestHttpException(sprintf('Parameter statementId ("%s") is not a valid UUID.', $id), $invalidArgumentException);
        }

        if (!$statementId->equals($statement->getId())) {
            throw new ConflictHttpException(sprintf('Id parameter ("%s") and statement id ("%s") do not match.', $id, $statement->getId()->getValue()));
        }

        return $statement;
    }
}
