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
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class StateOptionsControllerSpec extends ObjectBehavior
{
    public function it_should_throws_a_BadRequestHttpException_if_an_stateId_is_not_part_of_a_get_request(): void
    {
        $request = new Request();

        $this
            ->shouldThrow(BadRequestHttpException::class)
            ->during('optionsState', [$request]);
    }

    public function it_should_throws_a_BadRequestHttpException_if_an_stateId_is_a_string(): void
    {
        $request = new Request();
        $request->query->set('stateId', []);

        $this
            ->shouldThrow(BadRequestHttpException::class)
            ->during('optionsState', [$request]);
    }
}
