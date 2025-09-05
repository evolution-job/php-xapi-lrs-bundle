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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Xabbuh\XApi\Common\Exception\NotFoundException;
use Xabbuh\XApi\DataFixtures\StatementFixtures;
use Xabbuh\XApi\Model\StatementId;
use XApi\Repository\Api\StatementRepositoryInterface;


/**
 * @author Christian Flothmann <christian.flothmann@xabbuh.de>
 */
class StatementPutControllerSpec extends ObjectBehavior
{
    public function it_throws_a_BadRequestHttpException_if_a_statement_id_is_not_part_of_a_put_request(StatementRepositoryInterface $statementRepository): void
    {
        $statement = StatementFixtures::getTypicalStatement();
        $request = new Request();

        $this->beConstructedWith($statementRepository);

        $this
            ->shouldThrow(BadRequestHttpException::class)
            ->during('putStatement', [$request, $statement]);
    }

    public function it_throws_a_BadRequestHttpException_if_the_given_statement_id_as_part_of_a_put_request_is_not_a_valid_uuid(StatementRepositoryInterface $statementRepository): void
    {
        $statement = StatementFixtures::getTypicalStatement();
        $request = new Request();
        $request->query->set('statementId', 'invalid-uuid');

        $this->beConstructedWith($statementRepository);

        $this
            ->shouldThrow(BadRequestHttpException::class)
            ->during('putStatement', [$request, $statement]);
    }

    public function it_stores_a_statement_and_returns_a_204_response_if_the_statement_did_not_exist_before(StatementRepositoryInterface $statementRepository): void
    {
        $statement = StatementFixtures::getTypicalStatement();
        $request = new Request();
        $request->query->set('statementId', $statement->getId()->getValue());

        $statementRepository->findStatementById($statement->getId())->willThrow(new NotFoundException(''));
        $statementRepository->storeStatement($statement)->shouldBeCalled()->willReturn($statement->getId());

        $this->beConstructedWith($statementRepository);

        $response = $this->putStatement($request, $statement);

        $response->shouldHaveType(Response::class);
        $response->getStatusCode()->shouldReturn(Response::HTTP_OK);
    }

    public function it_throws_a_ConflictHttpException_if_the_id_parameter_and_the_statement_id_do_not_match_during_a_put_request(StatementRepositoryInterface $statementRepository): void
    {
        $statement = StatementFixtures::getTypicalStatement();
        $statementId = StatementId::fromString('39e24cc4-69af-4b01-a824-1fdc6ea8a3af');
        $request = new Request();
        $request->query->set('statementId', $statementId->getValue());

        $this->beConstructedWith($statementRepository);

        $this
            ->shouldThrow(ConflictHttpException::class)
            ->during('putStatement', [$request, $statement]);
    }

    public function it_uses_id_parameter_in_put_request_if_statement_id_is_null(StatementRepositoryInterface $statementRepository): void
    {
        $statement = StatementFixtures::getTypicalStatement();

        $statementId = $statement->getId();
        $request = new Request();
        $request->query->set('statementId', $statementId->getValue());

        $statementRepository->findStatementById($statementId)->shouldBeCalled()->willReturn($statement);

        $this->beConstructedWith($statementRepository);

        $statement = $statement->withId(null);
        $this->putStatement($request, $statement);
    }

    public function it_does_not_override_an_existing_statement(StatementRepositoryInterface $statementRepository): void
    {
        $statement = StatementFixtures::getTypicalStatement();
        $request = new Request();
        $request->query->set('statementId', $statement->getId()->getValue());

        $statementRepository->findStatementById($statement->getId())->willReturn($statement);
        $statementRepository->storeStatement($statement, true)->shouldNotBeCalled();

        $this->beConstructedWith($statementRepository);

        $this->putStatement($request, $statement);
    }

    public function it_throws_a_ConflictHttpException_if_an_existing_statement_with_the_same_id_is_not_equal_during_a_put_request(StatementRepositoryInterface $statementRepository): void
    {
        $statement = StatementFixtures::getTypicalStatement();
        $existingStatement = StatementFixtures::getAttachmentStatement()->withId($statement->getId());
        $request = new Request();
        $request->query->set('statementId', $statement->getId()->getValue());

        $statementRepository->findStatementById($statement->getId())->willReturn($existingStatement);

        $this->beConstructedWith($statementRepository);

        $this
            ->shouldThrow(ConflictHttpException::class)
            ->during('putStatement', [$request, $statement]);
    }
}
