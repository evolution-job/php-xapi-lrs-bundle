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
use Xabbuh\XApi\DataFixtures\StateFixtures;
use Xabbuh\XApi\Model\State;
use XApi\LrsBundle\Response\JsonResponse;
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
        $response->shouldReturnAnInstanceOf(JsonResponse::class);

        $response->getStatusCode()->shouldReturn(Response::HTTP_OK);
    }

    public function it_returns_an_empty_state_id_list_with_http_status_code_ok_when_no_states_exist(StateRepositoryInterface $stateRepository, Request $request): void
    {
        $typicalState = StateFixtures::getMinimalState();
        $state = new State($typicalState->getActivity(), $typicalState->getAgent(), null);

        $stateRepository->findState($state)->willReturn(null);
        $stateRepository->findStates($state)->willReturn([]);
        $request->isMethod(Request::METHOD_HEAD)->willReturn(false);

        $this->beConstructedWith($stateRepository);

        $response = $this->getState($request, $state);
        $response->shouldReturnAnInstanceOf(JsonResponse::class);

        $response->getStatusCode()->shouldReturn(Response::HTTP_OK);
        $response->getContent()->shouldReturn('[]');
    }
}
