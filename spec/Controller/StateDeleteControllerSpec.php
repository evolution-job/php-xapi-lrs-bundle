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
class StateDeleteControllerSpec extends ObjectBehavior
{
    public function it_returns_empty_data_and_http_status_code_no_content_when_state_is_remove(StateRepositoryInterface $stateRepository): void
    {
        $state = StateFixtures::getTypicalState();

        $stateRepository->removeState($state)->shouldBeCalled();
        $stateRepository->findState($state)->willReturn($state);

        $this->beConstructedWith($stateRepository);

        $response = $this->deleteState($state);

        $response->shouldReturnAnInstanceOf(XapiJsonResponse::class);

        $response->getStatusCode()->shouldReturn(Response::HTTP_NO_CONTENT);
    }
}
