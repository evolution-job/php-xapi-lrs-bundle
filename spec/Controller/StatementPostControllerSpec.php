<?php

/*
 * This file is part of the xAPI package.
 *
 * (c) Christian Flothmann <christian.flothmann@xabbuh.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace spec\XApi\LrsBundle\Controller;

use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use Symfony\Component\HttpFoundation\Response;
use Xabbuh\XApi\Common\Exception\ConflictException;
use Xabbuh\XApi\Common\Exception\NotFoundException;
use Xabbuh\XApi\DataFixtures\StatementFixtures;
use Xabbuh\XApi\Model\StatementId;
use XApi\LrsBundle\Response\JsonResponse;
use XApi\Repository\Api\StatementRepositoryInterface;

/**
 * @author Mathieu Boldo <mathieu.boldo@entrili.com>
 */
class StatementPostControllerSpec extends ObjectBehavior
{
    public function it_assigns_an_id_if_the_statement_does_not_have_one(StatementRepositoryInterface $statementRepository): void
    {
        $statement = StatementFixtures::getTypicalStatement()->withId();

        $statementRepository->findStatementById(Argument::type(StatementId::class))->willThrow(new NotFoundException(''));
        $statementRepository->storeStatement(Argument::that(static fn($storedStatement): bool => null !== $storedStatement->getId()))
            ->will(static fn($arguments) => $arguments[0]->getId());

        $this->beConstructedWith($statementRepository);

        $response = $this->postStatement($statement);

        $response->getStatusCode()->shouldReturn(Response::HTTP_OK);
        $response->getContent()->shouldMatch('/^\["[0-9a-f-]{36}"\]$/');
    }

    public function it_stores_a_statement_and_returns_a_204_response_if_the_statement_did_not_exist_before(StatementRepositoryInterface $statementRepository): void
    {
        $statement = StatementFixtures::getTypicalStatement();

        $statementRepository->findStatementById($statement->getId())->willThrow(new NotFoundException(''));
        $statementRepository->storeStatement($statement)->shouldBeCalled()->willReturn($statement->getId());

        $this->beConstructedWith($statementRepository);

        $response = $this->postStatement($statement);

        $response->shouldHaveType(Response::class);
        $response->getStatusCode()->shouldReturn(Response::HTTP_OK);
    }

    public function it_does_not_override_an_existing_statement(StatementRepositoryInterface $statementRepository): void
    {
        $statement = StatementFixtures::getTypicalStatement();

        $statementRepository->findStatementById($statement->getId())->willReturn($statement);
        $statementRepository->storeStatement($statement)->shouldNotBeCalled();

        $this->beConstructedWith($statementRepository);

        $this->postStatement($statement);
    }

    public function it_throws_a_ConflictException_if_an_existing_statement_with_the_same_id_is_not_equal_during_a_post_request(StatementRepositoryInterface $statementRepository): void
    {
        $statement = StatementFixtures::getTypicalStatement();
        $existingStatement = StatementFixtures::getAttachmentStatement()->withId($statement->getId());

        $statementRepository->findStatementById($statement->getId())->willReturn($existingStatement);

        $this->beConstructedWith($statementRepository);

        $this
            ->shouldThrow(ConflictException::class)
            ->during('postStatement', [$statement]);
    }

    public function it_stores_statements_and_returns_a_204_response_if_the_statement_did_not_exist_before(StatementRepositoryInterface $statementRepository): void
    {
        $statements = [];
        $uuids = [];
        foreach (StatementFixtures::getStatementCollection() as $statement) {
            $statements[] = $statement;
            $uuids[] = $statement->getId()->getValue();
            $statementRepository->findStatementById($statement->getId())->willThrow(new NotFoundException(''));
        }

        foreach ($statements as $index => $statement) {
            $statementRepository->storeStatement($statement, $index === count($statements) - 1)
                ->shouldBeCalled()
                ->willReturn($statement->getId());
        }

        $this->beConstructedWith($statementRepository);

        $response = $this->postStatements($statements);

        $response->shouldHaveType(JsonResponse::class);
        $response->getStatusCode()->shouldReturn(Response::HTTP_OK);
        $response->getContent()->shouldReturn(json_encode($uuids, JSON_THROW_ON_ERROR));
    }
}
