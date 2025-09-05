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
use Symfony\Component\HttpFoundation\Response;
use Xabbuh\XApi\DataFixtures\StateFixtures;
use XApi\LrsBundle\Response\XapiJsonResponse;
use XApi\Repository\Api\StateRepositoryInterface;

/**
 * @author Mathieu Boldo <mathieu.boldo@entrili.com>
 */
class StatePostControllerSpec extends ObjectBehavior
{
    public function it_should_store_a_state(StateRepositoryInterface $stateRepositoryInterface): void
    {
        $state = StateFixtures::getTypicalState();

        $stateRepositoryInterface->storeState($state)->shouldBeCalled();

        $this->beConstructedWith($stateRepositoryInterface);

        $response = $this->postState($state);

        $dateTime = new DateTime();
        $response->shouldHaveType(XapiJsonResponse::class);
        $response->getStatusCode()->shouldReturn(Response::HTTP_OK);
        $response->headers->get('X-Experience-API-Consistent-Through')->shouldReturn($dateTime->format('Y-m-d\TH:i:sP'));
    }
}
