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
use XApi\LrsBundle\Response\XapiJsonResponse;
use XApi\Repository\Api\StateRepositoryInterface;

/**
 * @author Mathieu Boldo <mathieu.boldo@entrili.com>
 */
class StateGetControllerSpec extends ObjectBehavior
{
    public function it_returns_data_and_http_status_code_ok_when_state_found(StateRepositoryInterface $stateRepository): void
    {
        $state = StateFixtures::getTypicalState();

        $stateRepository->findState($state)->willReturn($state);

        $this->beConstructedWith($stateRepository);

        $response = $this->getState($state);
        $response->shouldReturnAnInstanceOf(XapiJsonResponse::class);

        $response->getStatusCode()->shouldReturn(Response::HTTP_OK);
    }

    public function it_returns_empty_json_response_with_http_status_code_not_found_when_not_exists(StateRepositoryInterface $stateRepository): void
    {
        $state = StateFixtures::getMinimalState();

        $stateRepository->findState($state)->willReturn(null);

        $this->beConstructedWith($stateRepository);

        $response = $this->getState($state);
        $response->shouldReturnAnInstanceOf(XapiJsonResponse::class);

        $response->getStatusCode()->shouldReturn(Response::HTTP_NOT_FOUND);
    }
}
