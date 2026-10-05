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
use Xabbuh\XApi\DataFixtures\StateFixtures;
use Xabbuh\XApi\Model\State;
use XApi\LrsBundle\Response\JsonResponse;
use XApi\Repository\Api\StateRepositoryInterface;

/**
 * @author Mathieu Boldo <mathieu.boldo@entrili.com>
 */
class StateDeleteControllerSpec extends ObjectBehavior
{
    public function it_returns_empty_data_and_http_status_code_no_content_when_state_is_remove(StateRepositoryInterface $stateRepository): void
    {
        $state = StateFixtures::getTypicalState();

        $stateRepository->removeState($state)->shouldBeCalled();
        $stateRepository->findState($state)->willReturn($state);

        $this->beConstructedWith($stateRepository);

        $response = $this->deleteState($state);

        $response->shouldReturnAnInstanceOf(JsonResponse::class);

        $response->getStatusCode()->shouldReturn(Response::HTTP_NO_CONTENT);
    }

    public function it_removes_all_matching_states_when_state_id_is_omitted(StateRepositoryInterface $stateRepository): void
    {
        $typicalState = StateFixtures::getMinimalState();
        $state = new State($typicalState->getActivity(), $typicalState->getAgent(), null);

        $stateRepository->findState($state)->shouldNotBeCalled();
        $stateRepository->removeState($state)->shouldBeCalled();

        $this->beConstructedWith($stateRepository);

        $response = $this->deleteState($state);

        $response->shouldReturnAnInstanceOf(JsonResponse::class);
        $response->getStatusCode()->shouldReturn(Response::HTTP_NO_CONTENT);
    }
}
