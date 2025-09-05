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
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Xabbuh\XApi\Common\Exception\NotFoundException;
use Xabbuh\XApi\DataFixtures\StatementFixtures;
use XApi\LrsBundle\Response\XapiJsonResponse;
use XApi\Repository\Api\StatementRepositoryInterface;

/**
 * @author Mathieu Boldo <mathieu.boldo@entrili.com>
 */
class StatementPostControllerSpec extends ObjectBehavior
{
    public function it_throws_a_BadRequestHttpException_if_a_statement_id_is_not_part_of_a_post_request(StatementRepositoryInterface $statementRepository): void
    {
        $statement = StatementFixtures::getTypicalStatement()->withId(null);

        $this->beConstructedWith($statementRepository);

        $this
            ->shouldThrow(BadRequestHttpException::class)
            ->during('postStatement', [$statement]);
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

    public function it_throws_a_ConflictHttpException_if_an_existing_statement_with_the_same_id_is_not_equal_during_a_post_request(StatementRepositoryInterface $statementRepository): void
    {
        $statement = StatementFixtures::getTypicalStatement();
        $existingStatement = StatementFixtures::getAttachmentStatement()->withId($statement->getId());

        $statementRepository->findStatementById($statement->getId())->willReturn($existingStatement);

        $this->beConstructedWith($statementRepository);

        $this
            ->shouldThrow(ConflictHttpException::class)
            ->during('postStatement', [$statement]);
    }

    public function it_stores_statements_and_returns_a_204_response_if_the_statement_did_not_exist_before(StatementRepositoryInterface $statementRepository): void
    {
        $statements = [];
        $uuids = [];
        foreach(StatementFixtures::getStatementCollection() as $statement) {
            $statements[] = $statement;
            $uuids[] = $statement->getId()->getValue();
            $statementRepository->findStatementById($statement->getId())->willThrow(new NotFoundException(''));
            $statementRepository->storeStatement($statement)->shouldBeCalled()->willReturn($statement->getId());
        }

        $this->beConstructedWith($statementRepository);

        $response = $this->postStatements($statements);

        $response->shouldHaveType(XapiJsonResponse::class);
        $response->getStatusCode()->shouldReturn(Response::HTTP_OK);
        $response->getContent()->shouldReturn(json_encode($uuids, JSON_THROW_ON_ERROR));
    }
}
