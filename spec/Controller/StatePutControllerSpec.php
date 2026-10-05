<?php

/*
 * This file is part of the xAPI package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace spec\XApi\LrsBundle\Controller;

use PhpSpec\ObjectBehavior;
use Symfony\Component\HttpFoundation\Response;
use Xabbuh\XApi\DataFixtures\StateFixtures;
use XApi\LrsBundle\Response\JsonResponse;
use XApi\Repository\Api\StateRepositoryInterface;

/**
 * @author Mathieu Boldo <mathieu.boldo@entrili.com>
 */
class StatePutControllerSpec extends ObjectBehavior
{
    public function it_should_store_a_state(StateRepositoryInterface $stateRepository): void
    {
        $state = StateFixtures::getTypicalState();

        $stateRepository->storeState($state)->shouldBeCalled();

        $this->beConstructedWith($stateRepository);

        $response = $this->putState($state);

        $response->shouldHaveType(JsonResponse::class);
        $response->getStatusCode()->shouldReturn(Response::HTTP_NO_CONTENT);
        $response->getContent()->shouldReturn('');
        $response->headers->get('X-Experience-API-Consistent-Through')->shouldNotBe(null);
    }
}
