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
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Router;
use Xabbuh\XApi\Common\Exception\NotFoundException;
use Xabbuh\XApi\DataFixtures\StatementFixtures;
use Xabbuh\XApi\Model\IRL;
use Xabbuh\XApi\Model\Statement;
use Xabbuh\XApi\Model\StatementId;
use Xabbuh\XApi\Model\StatementResult;
use Xabbuh\XApi\Model\StatementsFilter;
use Xabbuh\XApi\Serializer\ActivitySerializerInterface;
use Xabbuh\XApi\Serializer\StatementResultSerializerInterface;
use Xabbuh\XApi\Serializer\StatementSerializerInterface;
use XApi\Fixtures\Json\StatementJsonFixtures;
use XApi\Fixtures\Json\StatementResultJsonFixtures;
use XApi\LrsBundle\Exception\BadRequestHttpException;
use XApi\LrsBundle\Exception\NotFoundHttpException;
use XApi\LrsBundle\Model\StatementsFilterFactory;
use XApi\LrsBundle\Response\JsonResponse;
use XApi\LrsBundle\Response\MultipartResponse;
use XApi\LrsBundle\Service\StatementContinuationManager;
use XApi\LrsBundle\Service\StatementFormatNormalizer;
use XApi\LrsBundle\Service\StatementGetQueryValidator;
use XApi\Repository\Api\ActivityRepositoryInterface;
use XApi\Repository\Api\StatementRepositoryInterface;
use XApi\Repository\Api\VerbRepositoryInterface;

/**
 * @author Christian Flothmann <christian.flothmann@xabbuh.de>
 * @author Mathieu Boldo <mathieu.boldo@entrili.com>
 */
class StatementGetControllerSpec extends ObjectBehavior
{
    public function let(
        Router $router,
        StatementRepositoryInterface $statementRepository,
        StatementSerializerInterface $statementSerializer,
        StatementResultSerializerInterface $statementResultSerializer,
        StatementsFilterFactory $statementsFilterFactory,
        ActivityRepositoryInterface $activityRepository,
        VerbRepositoryInterface $verbRepository,
        ActivitySerializerInterface $activitySerializer
    ): void {

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

        $this->beConstructedWith(
            $statementRepository,
            $statementResultSerializer,
            $statementSerializer,
            new StatementFormatNormalizer(
                $activityRepository->getWrappedObject(),
                $verbRepository->getWrappedObject(),
                $activitySerializer->getWrappedObject()
            ),
            new StatementGetQueryValidator($statementsFilterFactory->getWrappedObject()),
            new StatementContinuationManager(new ArrayAdapter(), $router->getWrappedObject())
        );
    }

    public function it_throws_a_BadRequestHttpException_if_the_request_has_given_statement_id_and_voided_statement_id(): void
    {
        $request = new Request();
        $request->query->set('statementId', StatementFixtures::DEFAULT_STATEMENT_ID);
        $request->query->set('voidedStatementId', StatementFixtures::DEFAULT_STATEMENT_ID);

        $this
            ->shouldThrow(BadRequestHttpException::class)
            ->during('getStatements', [$request]);
    }

    public function it_throws_a_BadRequestHttpException_if_the_request_has_statement_id_and_format_and_attachements_and_any_other_parameters(): void
    {
        $request = new Request();
        $request->query->set('statementId', StatementFixtures::DEFAULT_STATEMENT_ID);
        $request->query->set('format', 'ids');
        $request->query->set('attachments', false);
        $request->query->set('related_agents', false);

        $this
            ->shouldThrow(new BadRequestHttpException('Request must not contain statementId or voidedStatementId parameters, and also any other parameter like "related_agents" besides "attachments" or "format".'))
            ->during('getStatements', [$request]);
    }

    public function it_throws_a_BadRequestHttpException_if_the_request_has_voided_statement_id_and_format_and_any_other_parameters_except_attachments(): void
    {
        $request = new Request();
        $request->query->set('voidedStatementId', StatementFixtures::DEFAULT_STATEMENT_ID);
        $request->query->set('format', 'ids');
        $request->query->set('related_agents', false);

        $this
            ->shouldThrow(new BadRequestHttpException('Request must not contain statementId or voidedStatementId parameters, and also any other parameter like "related_agents" besides "attachments" or "format".'))
            ->during('getStatements', [$request]);
    }

    public function it_throws_a_BadRequestHttpException_if_the_request_has_statement_id_and_attachments_and_any_other_parameters_except_format(): void
    {
        $request = new Request();
        $request->query->set('statementId', StatementFixtures::DEFAULT_STATEMENT_ID);
        $request->query->set('attachments', false);
        $request->query->set('related_agents', false);

        $this
            ->shouldThrow(new BadRequestHttpException('Request must not contain statementId or voidedStatementId parameters, and also any other parameter like "related_agents" besides "attachments" or "format".'))
            ->during('getStatements', [$request]);
    }

    public function it_throws_a_BadRequestHttpException_if_the_request_has_voided_statement_id_and_any_other_parameters_except_format_and_attachments(): void
    {
        $request = new Request();
        $request->query->set('voidedStatementId', StatementFixtures::DEFAULT_STATEMENT_ID);
        $request->query->set('related_agents', false);

        $this
            ->shouldThrow(new BadRequestHttpException('Request must not contain statementId or voidedStatementId parameters, and also any other parameter like "related_agents" besides "attachments" or "format".'))
            ->during('getStatements', [$request]);
    }

    public function it_sets_a_X_Experience_API_Consistent_Through_header_to_the_response(): void
    {
        $request = new Request();
        $request->query->set('statementId', StatementFixtures::DEFAULT_STATEMENT_ID);

        $response = $this->getStatements($request);

        /** @var ResponseHeaderBag $headers */
        $headers = $response->headers;

        $headers->has('X-Experience-API-Consistent-Through')->shouldBe(true);
    }

    public function it_includes_a_Last_Modified_Header_if_a_single_statement_is_fetched(): void
    {
        $request = new Request();
        $request->query->set('statementId', StatementFixtures::DEFAULT_STATEMENT_ID);

        $response = $this->getStatements($request);

        /** @var ResponseHeaderBag $headers */
        $headers = $response->headers;

        $headers->has('Last-Modified')->shouldBe(true);

        $request = new Request();
        $request->query->set('voidedStatementId', StatementFixtures::DEFAULT_STATEMENT_ID);

        $response = $this->getStatements($request);

        /** @var ResponseHeaderBag $headers */
        $headers = $response->headers;

        $headers->has('Last-Modified')->shouldBe(true);
    }

    public function it_returns_a_multipart_response_if_attachments_parameter_is_true(): void
    {
        $request = new Request();
        $request->query->set('attachments', true);

        $this->getStatements($request)->shouldReturnAnInstanceOf(MultipartResponse::class);
    }

    public function it_returns_a_JsonXapiResponse_if_attachments_parameter_is_false_or_not_set(): void
    {
        $request = new Request();

        $this->getStatements($request)->shouldReturnAnInstanceOf(JsonResponse::class);

        $request->query->set('attachments', false);

        $this->getStatements($request)->shouldReturnAnInstanceOf(JsonResponse::class);
    }

    public function it_should_fetch_a_statement(StatementRepositoryInterface $statementRepository): void
    {
        $request = new Request();
        $request->query->set('statementId', StatementFixtures::DEFAULT_STATEMENT_ID);

        $statementRepository->findStatementById(StatementId::fromString(StatementFixtures::DEFAULT_STATEMENT_ID))->shouldBeCalled();

        $this->getStatements($request);
    }

    public function it_should_fetch_a_voided_statement_id(StatementRepositoryInterface $statementRepository): void
    {
        $request = new Request();
        $request->query->set('voidedStatementId', StatementFixtures::DEFAULT_STATEMENT_ID);

        $statementRepository->findVoidedStatementById(StatementId::fromString(StatementFixtures::DEFAULT_STATEMENT_ID))->shouldBeCalled();

        $this->getStatements($request);
    }

    public function it_should_filter_all_statements_if_no_statement_id_or_voided_statement_id_is_provided(StatementRepositoryInterface $statementRepository): void
    {
        $request = new Request();

        $statementRepository->findStatementsBy(Argument::type(StatementsFilter::class))->shouldBeCalled();

        $this->getStatements($request);
    }

    public function it_throws_not_found_if_a_statement_id_does_not_exist(
        StatementRepositoryInterface $statementRepository,
    ): void {

        $request = new Request();
        $request->query->set('statementId', StatementFixtures::DEFAULT_STATEMENT_ID);

        $statementRepository->findStatementById(StatementId::fromString(StatementFixtures::DEFAULT_STATEMENT_ID))->willThrow(NotFoundException::class);

        $this->shouldThrow(NotFoundHttpException::class)->during('getStatements', [$request]);
    }

    public function it_throws_not_found_if_a_voided_statement_id_does_not_exist(
        StatementRepositoryInterface $statementRepository,
    ): void {
        $request = new Request();
        $request->query->set('voidedStatementId', StatementFixtures::DEFAULT_STATEMENT_ID);

        $statementRepository->findVoidedStatementById(StatementId::fromString(StatementFixtures::DEFAULT_STATEMENT_ID))->willThrow(NotFoundException::class);

        $this->shouldThrow(NotFoundHttpException::class)->during('getStatements', [$request]);
    }

    public function it_rejects_unrecognized_query_parameters(): void
    {
        $request = new Request();
        $request->query->set('unknown', 'value');

        $this->shouldThrow(BadRequestHttpException::class)->during('getStatements', [$request]);
    }

    public function it_rejects_unsupported_statement_formats(): void
    {
        $request = new Request();
        $request->query->set('format', 'other');

        $this->shouldThrow(BadRequestHttpException::class)->during('getStatements', [$request]);
    }

    public function it_returns_a_paginated_envelope_for_statements_list(
        Request $request,
        Router $router,
        StatementRepositoryInterface $statementRepository,
        StatementResultSerializerInterface $statementResultSerializer,
        StatementsFilterFactory $statementFilterFactory,
    ) {
        $request->query = new InputBag(['limit' => 1, 'offset' => 1]);
        $request->isMethod(Request::METHOD_HEAD)->willReturn(false);
        $filter = new StatementsFilter();
        $statementFilterFactory->createFromParameterBag(Argument::type(ParameterBag::class))->willReturn($filter);

        $statements = StatementFixtures::getStatementCollection();
        $statements[] = $statements[0];
        $statementRepository->findStatementsBy($filter)->willReturn($statements);
        $router->generate(
            'xapi_lrs.statement.get',
            Argument::that(static fn(array $parameters): bool => 1 === count($parameters)
                && isset($parameters['moreId'])
                && 1 === preg_match('/\A[a-f0-9]{64}\z/', $parameters['moreId']))
        )->willReturn('/statements?moreId='.str_repeat('a', 64));
        $more = IRL::fromString('/statements?moreId='.str_repeat('a', 64));
        $statementResult = new StatementResult([$statements[1]], $more);

        $statementResultSerializer->serializeStatementResult($statementResult)->shouldBeCalled()->willReturn(StatementResultJsonFixtures::getStatementResultWithMore());

        $response = $this->getStatements($request);

        $response->shouldHaveType(JsonResponse::class);
        $response->getStatusCode()->shouldReturn(Response::HTTP_OK);

        $response->getContent()->shouldContain('"statements":');
        $response->getContent()->shouldContain('"more":');
    }
}
