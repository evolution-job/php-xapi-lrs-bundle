<?php

/*
 * This file is part of the xAPI package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace spec\XApi\LrsBundle\Controller;

use PhpSpec\ObjectBehavior;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use XApi\LrsBundle\Controller\StateOptionsController;

/**
 * @author Mathieu Boldo <mathieu.boldo@entrili.com>
 */
class StateOptionsControllerSpec extends ObjectBehavior
{
    public function it_is_initializable(): void
    {
        $this->shouldHaveType(StateOptionsController::class);
    }

    public function it_returns_a_204_response_if_required_parameters_are_missing(Request $request, ParameterBag $parameterBag): void
    {
        $request->query = new InputBag([]);
        $parameterBag->get('activityId')->willReturn(null);
        $parameterBag->get('agent')->willReturn(null);

        $response = $this->optionsState($request);

        $response->shouldHaveType(Response::class);
        $response->getStatusCode()->shouldReturn(Response::HTTP_NO_CONTENT);
    }

    public function it_returns_a_204_response_if_parameters_are_present(Request $request, ParameterBag $parameterBag): void
    {
        $request->query = new InputBag([]);
        $parameterBag->get('activityId')->willReturn('http://example.com');
        $parameterBag->get('agent')->willReturn('{"mbox":"mailto:test@example.com"}');

        $response = $this->optionsState($request);

        $response->shouldHaveType(Response::class);
        $response->getStatusCode()->shouldReturn(Response::HTTP_NO_CONTENT);
    }
}
