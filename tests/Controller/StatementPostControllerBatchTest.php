<?php

/*
 * This file is part of the xAPI package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace XApi\LrsBundle\Tests\Controller;

use PHPUnit\Framework\TestCase;
use Xabbuh\XApi\Common\Exception\BadRequestException;
use Xabbuh\XApi\Common\Exception\ConflictException;
use Xabbuh\XApi\Common\Exception\NotFoundException;
use Xabbuh\XApi\DataFixtures\StatementFixtures;
use XApi\LrsBundle\Controller\StatementPostController;
use XApi\Repository\Api\StatementRepositoryInterface;

/**
 * @author Mathieu Boldo <mathieu.boldo@entrili.com>
 */
class StatementPostControllerBatchTest extends TestCase
{
    public function testConflictingStatementRejectsBatchBeforeAnyStatementIsStored(): void
    {
        $newStatement = StatementFixtures::getMinimalStatement('12345678-1234-5678-8234-567812345678');
        $conflictingStatement = StatementFixtures::getMinimalStatement('12345678-1234-5678-8234-567812345679');
        $existingStatement = StatementFixtures::getAttachmentStatement()
            ->withId($conflictingStatement->getId());

        $repository = $this->createMock(StatementRepositoryInterface::class);
        $repository->expects($this->exactly(2))
            ->method('findStatementById')
            ->willReturnCallback(static function ($id) use ($newStatement, $conflictingStatement, $existingStatement) {
                if ($id->equals($newStatement->getId())) {
                    throw new NotFoundException('Not found');
                }

                self::assertTrue($id->equals($conflictingStatement->getId()));

                return $existingStatement;
            });
        $repository->expects($this->never())->method('storeStatement');

        $controller = new StatementPostController($repository);

        $this->expectException(ConflictException::class);
        $controller->postStatements([$newStatement, $conflictingStatement]);
    }

    public function testDuplicateIdsRejectBatchBeforeRepositoryAccess(): void
    {
        $statement = StatementFixtures::getMinimalStatement();
        $repository = $this->createMock(StatementRepositoryInterface::class);
        $repository->expects($this->never())->method('findStatementById');
        $repository->expects($this->never())->method('storeStatement');

        $controller = new StatementPostController($repository);

        $this->expectException(BadRequestException::class);
        $controller->postStatements([$statement, $statement]);
    }

    public function testStorageFailureIsNotSilentlyIgnored(): void
    {
        $statement = StatementFixtures::getMinimalStatement();
        $repository = $this->createMock(StatementRepositoryInterface::class);
        $repository->method('findStatementById')->willThrowException(new NotFoundException('Not found'));
        $repository->expects($this->once())
            ->method('storeStatement')
            ->willThrowException(new \RuntimeException('Storage unavailable'));

        $controller = new StatementPostController($repository);

        $this->expectException(\RuntimeException::class);
        $controller->postStatements([$statement]);
    }
}
