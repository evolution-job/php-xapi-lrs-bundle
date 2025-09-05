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
use XApi\LrsBundle\Response\XapiJsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Xabbuh\XApi\Common\Exception\NotFoundException;
use Xabbuh\XApi\Model\Statement;
use XApi\Repository\Api\StatementRepositoryInterface;

/**
 * @author Mathieu Boldo <mathieu.boldo@entrili.com>
 */
final readonly class StatementPostController
{
    public function __construct(private StatementRepositoryInterface $statementRepository) { }

    public function postStatement(Request $request, Statement $statement): XapiJsonResponse
    {
        if (null === $statementId = $request->query->all()['statementId'] ?? null) {
            throw new BadRequestHttpException('Required statementId parameter is missing.');
        }

        if (!is_string($statementId)) {
            throw new BadRequestHttpException('Required statementId parameter is not a string.');
        }

        $this->storeStatement($statementId, $statement);

        return new XapiJsonResponse($statement->getId(), Response::HTTP_OK);
    }

    public function postStatements(array $statements): XapiJsonResponse
    {
        $uuids = [];

        /** @var Statement $statement */
        foreach ($statements as $statement) {

            try {
                $this->storeStatement($statement->getId()?->getValue(), $statement);
                $uuids[] = $statement->getId()?->getValue();
            } catch (Exception) {
                // Ignore...
            }
        }

        return new XapiJsonResponse($uuids, Response::HTTP_OK);
    }

    private function storeStatement(string $id, Statement $statement): void
    {
        $statement = StatementPutController::resolveStatement($id, $statement);

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
