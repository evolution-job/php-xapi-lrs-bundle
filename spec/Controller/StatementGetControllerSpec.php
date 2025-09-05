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

use DateTime;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Xabbuh\XApi\Common\Exception\NotFoundException;
use Xabbuh\XApi\DataFixtures\StatementFixtures;
use Xabbuh\XApi\Model\Statement;
use Xabbuh\XApi\Model\StatementId;
use Xabbuh\XApi\Model\StatementResult;
use Xabbuh\XApi\Model\StatementsFilter;
use Xabbuh\XApi\Serializer\StatementResultSerializerInterface;
use Xabbuh\XApi\Serializer\StatementSerializerInterface;
use XApi\Fixtures\Json\StatementJsonFixtures;
use XApi\Fixtures\Json\StatementResultJsonFixtures;
use XApi\LrsBundle\Model\StatementsFilterFactory;
use XApi\LrsBundle\Response\MultipartResponse;
use XApi\LrsBundle\Response\XapiJsonResponse;
use XApi\Repository\Api\StatementRepositoryInterface;

/**
 * @author Christian Flothmann <christian.flothmann@xabbuh.de>
 */
class StatementGetControllerSpec extends ObjectBehavior
{
    public function let(StatementRepositoryInterface $statementRepository, StatementSerializerInterface $statementSerializer, StatementResultSerializerInterface $statementResultSerializer, StatementsFilterFactory $statementsFilterFactory): void
    {
        $statement = StatementFixtures::getAllPropertiesStatement();
        $voidedStatement = StatementFixtures::getVoidingStatement()->withStored(new DateTime());
        $statementCollection = StatementFixtures::getStatementCollection();
        $statementsFilter = new StatementsFilter();

        $statementsFilterFactory->createFromParameterBag(Argument::type(ParameterBag::class))->willReturn($statementsFilter);

        $statementRepository->findStatementById(StatementId::fromString(StatementFixtures::DEFAULT_STATEMENT_ID))->willReturn($statement);
        $statementRepository->findVoidedStatementById(StatementId::fromString(StatementFixtures::DEFAULT_STATEMENT_ID))->willReturn($voidedStatement);
        $statementRepository->findStatementsBy($statementsFilter)->willReturn($statementCollection);

        $statementSerializer->serializeStatement(Argument::type(Statement::class))->willReturn(StatementJsonFixtures::getTypicalStatement());

        $statementResultSerializer->serializeStatementResult(Argument::type(StatementResult::class))->willReturn(StatementResultJsonFixtures::getStatementResult());

        $this->beConstructedWith($statementRepository, $statementSerializer, $statementResultSerializer, $statementsFilterFactory);
    }

    public function it_throws_a_BadRequestHttpException_if_the_request_has_given_statement_id_and_voided_statement_id(): void
    {
        $request = new Request();
        $request->query->set('statementId', StatementFixtures::DEFAULT_STATEMENT_ID);
        $request->query->set('voidedStatementId', StatementFixtures::DEFAULT_STATEMENT_ID);

        $this
            ->shouldThrow(BadRequestHttpException::class)
            ->during('getStatement', [$request]);
    }

    public function it_throws_a_BadRequestHttpException_if_the_request_has_statement_id_and_format_and_attachements_and_any_other_parameters(): void
    {
        $request = new Request();
        $request->query->set('statementId', StatementFixtures::DEFAULT_STATEMENT_ID);
        $request->query->set('format', 'ids');
        $request->query->set('attachments', false);
        $request->query->set('related_agents', false);

        $this
            ->shouldThrow(new BadRequestHttpException('Cannot have "related_agents" parameters. Only "format" and/or "attachments" are allowed with "statementId" or "voidedStatementId".'))
            ->during('getStatement', [$request]);
    }

    public function it_throws_a_BadRequestHttpException_if_the_request_has_voided_statement_id_and_format_and_any_other_parameters_except_attachments(): void
    {
        $request = new Request();
        $request->query->set('voidedStatementId', StatementFixtures::DEFAULT_STATEMENT_ID);
        $request->query->set('format', 'ids');
        $request->query->set('related_agents', false);

        $this
            ->shouldThrow(new BadRequestHttpException('Cannot have "related_agents" parameters. Only "format" and/or "attachments" are allowed with "statementId" or "voidedStatementId".'))
            ->during('getStatement', [$request]);
    }

    public function it_throws_a_BadRequestHttpException_if_the_request_has_statement_id_and_attachments_and_any_other_parameters_except_format(): void
    {
        $request = new Request();
        $request->query->set('statementId', StatementFixtures::DEFAULT_STATEMENT_ID);
        $request->query->set('attachments', false);
        $request->query->set('related_agents', false);

        $this
            ->shouldThrow(new BadRequestHttpException('Cannot have "related_agents" parameters. Only "format" and/or "attachments" are allowed with "statementId" or "voidedStatementId".'))
            ->during('getStatement', [$request]);
    }

    public function it_throws_a_BadRequestHttpException_if_the_request_has_voided_statement_id_and_any_other_parameters_except_format_and_attachments(): void
    {
        $request = new Request();
        $request->query->set('voidedStatementId', StatementFixtures::DEFAULT_STATEMENT_ID);
        $request->query->set('related_agents', false);

        $this
            ->shouldThrow(new BadRequestHttpException('Cannot have "related_agents" parameters. Only "format" and/or "attachments" are allowed with "statementId" or "voidedStatementId".'))
            ->during('getStatement', [$request]);
    }

    public function it_sets_a_X_Experience_API_Consistent_Through_header_to_the_response(): void
    {
        $request = new Request();
        $request->query->set('statementId', StatementFixtures::DEFAULT_STATEMENT_ID);

        $response = $this->getStatement($request);

        /** @var ResponseHeaderBag $headers */
        $headers = $response->headers;

        $headers->has('X-Experience-API-Consistent-Through')->shouldBe(true);
    }

    public function it_includes_a_Last_Modified_Header_if_a_single_statement_is_fetched(): void
    {
        $request = new Request();
        $request->query->set('statementId', StatementFixtures::DEFAULT_STATEMENT_ID);

        $response = $this->getStatement($request);

        /** @var ResponseHeaderBag $headers */
        $headers = $response->headers;

        $headers->has('Last-Modified')->shouldBe(true);

        $request = new Request();
        $request->query->set('voidedStatementId', StatementFixtures::DEFAULT_STATEMENT_ID);

        $response = $this->getStatement($request);

        /** @var ResponseHeaderBag $headers */
        $headers = $response->headers;

        $headers->has('Last-Modified')->shouldBe(true);
    }

    public function it_returns_a_multipart_response_if_attachments_parameter_is_true(): void
    {
        $request = new Request();
        $request->query->set('attachments', true);

        $this->getStatement($request)->shouldReturnAnInstanceOf(MultipartResponse::class);
    }

    public function it_returns_a_XapiJsonResponse_if_attachments_parameter_is_false_or_not_set(): void
    {
        $request = new Request();

        $this->getStatement($request)->shouldReturnAnInstanceOf(XapiJsonResponse::class);

        $request->query->set('attachments', false);

        $this->getStatement($request)->shouldReturnAnInstanceOf(XapiJsonResponse::class);
    }

    public function it_should_fetch_a_statement(StatementRepositoryInterface $statementRepository): void
    {
        $request = new Request();
        $request->query->set('statementId', StatementFixtures::DEFAULT_STATEMENT_ID);

        $statementRepository->findStatementById(StatementId::fromString(StatementFixtures::DEFAULT_STATEMENT_ID))->shouldBeCalled();

        $this->getStatement($request);
    }

    public function it_should_fetch_a_voided_statement_id(StatementRepositoryInterface $statementRepository): void
    {
        $request = new Request();
        $request->query->set('voidedStatementId', StatementFixtures::DEFAULT_STATEMENT_ID);

        $statementRepository->findVoidedStatementById(StatementId::fromString(StatementFixtures::DEFAULT_STATEMENT_ID))->shouldBeCalled();

        $this->getStatement($request);
    }

    public function it_should_filter_all_statements_if_no_statement_id_or_voided_statement_id_is_provided(StatementRepositoryInterface $statementRepository): void
    {
        $request = new Request();

        $statementRepository->findStatementsBy(Argument::type(StatementsFilter::class))->shouldBeCalled();

        $this->getStatement($request);
    }

    public function it_should_build_an_empty_statement_result_response_if_no_statement_is_found(StatementRepositoryInterface $statementRepository, StatementResultSerializerInterface $statementResultSerializer): void
    {
        $request = new Request();
        $request->query->set('statementId', StatementFixtures::DEFAULT_STATEMENT_ID);

        $statementRepository->findStatementById(StatementId::fromString(StatementFixtures::DEFAULT_STATEMENT_ID))->willThrow(NotFoundException::class);

        $statementResultSerializer->serializeStatementResult(new StatementResult([]))->shouldBeCalled()->willReturn(StatementResultJsonFixtures::getStatementResult());

        $this->getStatement($request);
    }
}
