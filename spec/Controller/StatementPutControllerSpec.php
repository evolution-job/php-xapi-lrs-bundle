<?php

namespace spec\XApi\LrsBundle\Controller;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use PhpSpec\ObjectBehavior;
use Symfony\Component\HttpFoundation\Request;
use Xabbuh\XApi\Common\Exception\NotFoundException;
use Xabbuh\XApi\DataFixtures\StatementFixtures;
use Xabbuh\XApi\Model\StatementId;
use XApi\Repository\Api\StatementRepositoryInterface;

class StatementPutControllerSpec extends ObjectBehavior
{
    public function let(StatementRepositoryInterface $statementRepository): void
    {
        $this->beConstructedWith($statementRepository);
    }

    public function it_throws_a_badrequesthttpexception_if_a_statement_id_is_not_part_of_a_put_request(): void
    {
        $statement = StatementFixtures::getTypicalStatement();
        $request = new Request();

        $this
            ->shouldThrow(BadRequestHttpException::class)
            ->during('putStatement', [$request, $statement]);
    }

    public function it_throws_a_badrequesthttpexception_if_the_given_statement_id_as_part_of_a_put_request_is_not_a_valid_uuid(): void
    {
        $statement = StatementFixtures::getTypicalStatement();
        $request = new Request();
        $request->query->set('statementId', 'invalid-uuid');

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
        $statementRepository->storeStatement($statement, true)->shouldBeCalled();

        $response = $this->putStatement($request, $statement);

        $response->shouldHaveType(Response::class);
        $response->getStatusCode()->shouldReturn(204);
    }

    public function it_throws_a_conflicthttpexception_if_the_id_parameter_and_the_statement_id_do_not_match_during_a_put_request(): void
    {
        $statement = StatementFixtures::getTypicalStatement();
        $statementId = StatementId::fromString('39e24cc4-69af-4b01-a824-1fdc6ea8a3af');
        $request = new Request();
        $request->query->set('statementId', $statementId->getValue());

        $this
            ->shouldThrow(ConflictHttpException::class)
            ->during('putStatement', [$request, $statement]);
    }

    public function it_uses_id_parameter_in_put_request_if_statement_id_is_null(StatementRepositoryInterface $statementRepository): void
    {
        $statement = StatementFixtures::getTypicalStatement();
        $statementId = $statement->getId();
        $statement = $statement->withId(null);
        $request = new Request();
        $request->query->set('statementId', $statementId->getValue());

        $statementRepository->findStatementById($statementId)->willReturn($statement);
        $statementRepository->findStatementById($statementId)->shouldBeCalled();

        $this->putStatement($request, $statement);
    }

    public function it_does_not_override_an_existing_statement(StatementRepositoryInterface $statementRepository): void
    {
        $statement = StatementFixtures::getTypicalStatement();
        $request = new Request();
        $request->query->set('statementId', $statement->getId()->getValue());

        $statementRepository->findStatementById($statement->getId())->willReturn($statement);
        $statementRepository->storeStatement($statement, true)->shouldNotBeCalled();

        $this->putStatement($request, $statement);
    }

    public function it_throws_a_conflicthttpexception_if_an_existing_statement_with_the_same_id_is_not_equal_during_a_put_request(StatementRepositoryInterface $statementRepository): void
    {
        $statement = StatementFixtures::getTypicalStatement();
        $existingStatement = StatementFixtures::getAttachmentStatement()->withId($statement->getId());
        $request = new Request();
        $request->query->set('statementId', $statement->getId()->getValue());

        $statementRepository->findStatementById($statement->getId())->willReturn($existingStatement);

        $this
            ->shouldThrow(ConflictHttpException::class)
            ->during('putStatement', [$request, $statement]);
    }
}
