<?php

/*
 * This file is part of the xAPI package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace XApi\LrsBundle\Tests\Controller;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Xabbuh\XApi\Common\Exception\ConflictException;
use Xabbuh\XApi\Common\Exception\NotFoundException;
use Xabbuh\XApi\DataFixtures\StatementFixtures;
use XApi\LrsBundle\Controller\StatementPutController;
use XApi\Repository\Api\StatementRepositoryInterface;

/**
 * @author Mathieu Boldo <mathieu.boldo@entrili.com>
 */
class StatementPutControllerTest extends TestCase
{
    public function testPutStoresNewStatement(): void
    {
        $statement = StatementFixtures::getMinimalStatement();
        $repository = $this->createMock(StatementRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('findStatementById')
            ->with($statement->getId())
            ->willThrowException(new NotFoundException('Statement not found.'));
        $repository->expects($this->once())
            ->method('storeStatement')
            ->with($statement)
            ->willReturn($statement->getId());

        $response = new StatementPutController($repository)->putStatements(
            $this->requestFor($statement),
            $statement
        );

        self::assertSame(204, $response->getStatusCode());
    }

    public function testPutOfIdenticalStatementIsIdempotent(): void
    {
        $statement = StatementFixtures::getMinimalStatement();
        $repository = $this->createMock(StatementRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('findStatementById')
            ->with($statement->getId())
            ->willReturn($statement);
        $repository->expects($this->never())->method('storeStatement');

        $response = new StatementPutController($repository)->putStatements(
            $this->requestFor($statement),
            $statement
        );

        self::assertSame(204, $response->getStatusCode());
    }

    public function testPutOfDifferentStatementWithExistingIdConflicts(): void
    {
        $statement = StatementFixtures::getMinimalStatement();
        $existingStatement = StatementFixtures::getTypicalStatement();
        $repository = $this->createMock(StatementRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('findStatementById')
            ->with($statement->getId())
            ->willReturn($existingStatement);
        $repository->expects($this->never())->method('storeStatement');

        $this->expectException(ConflictException::class);

        new StatementPutController($repository)->putStatements(
            $this->requestFor($statement),
            $statement
        );
    }

    private function requestFor(\Xabbuh\XApi\Model\Statement $statement): Request
    {
        return Request::create('/statements?statementId='.$statement->getId()->getValue());
    }
}
