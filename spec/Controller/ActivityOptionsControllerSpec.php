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
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @author Mathieu Boldo <mathieu.boldo@entrili.com>
 */
class ActivityOptionsControllerSpec extends ObjectBehavior
{
    public function it_is_initializable()
    {
        $this->shouldHaveType('XApi\LrsBundle\Controller\ActivityOptionsController');
    }

    public function it_returns_a_204_response_if_the_activityId_parameter_is_missing(Request $request, ParameterBag $query)
    {
        $request->query = new InputBag([]);
        $query->get('activityId')->willReturn(null);

        $response = $this->optionsActivity($request);

        $response->shouldHaveType(Response::class);
        $response->getStatusCode()->shouldReturn(Response::HTTP_NO_CONTENT);
    }

    public function it_returns_a_204_response_if_the_activityId_parameter_is_present(Request $request, ParameterBag $query)
    {
        $request->query = new InputBag([]);
        $query->get('activityId')->willReturn('http://example.com');

        $response = $this->optionsActivity($request);

        $response->shouldHaveType(Response::class);
        $response->getStatusCode()->shouldReturn(Response::HTTP_NO_CONTENT);
    }
}
