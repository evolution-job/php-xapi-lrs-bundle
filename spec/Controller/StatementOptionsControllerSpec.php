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
class StatementOptionsControllerSpec extends ObjectBehavior
{
    public function it_is_initializable()
    {
        $this->shouldHaveType('XApi\LrsBundle\Controller\StatementOptionsController');
    }

    public function it_returns_a_204_response_if_the_statementId_parameter_is_missing(Request $request, ParameterBag $query)
    {
        $request->query = new InputBag([]);
        $query->get('statementId')->willReturn(null);

        $response = $this->optionsStatement($request);

        $response->shouldHaveType(Response::class);
        $response->getStatusCode()->shouldReturn(Response::HTTP_NO_CONTENT); // Vérifie le code 204
    }

    public function it_returns_a_200_response_if_the_statementId_parameter_is_present(Request $request, ParameterBag $query)
    {
        $request->query = new InputBag([]);
        $query->get('statementId')->willReturn('eaf1c3e2-be78-434a-ab70-4790b07f4c64');

        $response = $this->optionsStatement($request);

        $response->shouldHaveType(Response::class);

        $response->getStatusCode()->shouldReturn(Response::HTTP_NO_CONTENT);
    }
}
