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

use DateTimeImmutable;
use PhpSpec\ObjectBehavior;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Xabbuh\XApi\Common\Exception\BadRequestException;
use Xabbuh\XApi\DataFixtures\StateFixtures;
use Xabbuh\XApi\Model\State;
use XApi\LrsBundle\Response\JsonResponse;
use XApi\LrsBundle\Response\StateDocumentResponse;
use XApi\Repository\Api\StateRepositoryInterface;

/**
 * @author Mathieu Boldo <mathieu.boldo@entrili.com>
 */
class StateGetControllerSpec extends ObjectBehavior
{
    public function it_returns_data_and_http_status_code_ok_when_state_found(StateRepositoryInterface $stateRepository, Request $request): void
    {
        $state = StateFixtures::getTypicalState();

        $stateRepository->findState($state)->willReturn($state);
        $request->isMethod(Request::METHOD_HEAD)->willReturn(false);

        $this->beConstructedWith($stateRepository);

        $response = $this->getState($request, $state);
        $response->shouldReturnAnInstanceOf(StateDocumentResponse::class);

        $response->getStatusCode()->shouldReturn(Response::HTTP_OK);
    }

    public function it_returns_an_empty_state_id_list_with_http_status_code_ok_when_no_states_exist(StateRepositoryInterface $stateRepository): void
    {
        $typicalState = StateFixtures::getMinimalState();
        $state = new State($typicalState->getActivity(), $typicalState->getAgent(), null);
        $request = new Request();

        $stateRepository->findStates($state, null)->willReturn([]);

        $this->beConstructedWith($stateRepository);

        $response = $this->getState($request, $state);
        $response->shouldReturnAnInstanceOf(JsonResponse::class);

        $response->getStatusCode()->shouldReturn(Response::HTTP_OK);
        $response->getContent()->shouldReturn('[]');
    }

    public function it_filters_state_ids_by_since_exclusively(StateRepositoryInterface $stateRepository): void
    {
        $typicalState = StateFixtures::getMinimalState();
        $state = new State($typicalState->getActivity(), $typicalState->getAgent(), null);
        $since = new DateTimeImmutable('2024-01-01T00:00:00.000000+00:00');
        $request = new Request(['since' => '2024-01-01T00:00:00Z']);

        $stateRepository->findStates($state, $since)->willReturn([]);

        $this->beConstructedWith($stateRepository);

        $response = $this->getState($request, $state);

        $response->getContent()->shouldReturn('[]');
    }

    public function it_omits_the_body_for_head_state_list_requests(StateRepositoryInterface $stateRepository): void
    {
        $typicalState = StateFixtures::getMinimalState();
        $state = new State($typicalState->getActivity(), $typicalState->getAgent(), null);
        $request = new Request(server: ['REQUEST_METHOD' => Request::METHOD_HEAD]);

        $stateRepository->findStates($state, null)->willReturn([]);

        $this->beConstructedWith($stateRepository);

        $response = $this->getState($request, $state);

        $response->getContent()->shouldReturn('');
    }

    public function it_rejects_an_invalid_since_timestamp(StateRepositoryInterface $stateRepository): void
    {
        $typicalState = StateFixtures::getMinimalState();
        $state = new State($typicalState->getActivity(), $typicalState->getAgent(), null);
        $request = new Request(['since' => 'not-a-timestamp']);

        $this->beConstructedWith($stateRepository);

        $this
            ->shouldThrow(BadRequestException::class)
            ->during('getState', [$request, $state]);
    }
}
