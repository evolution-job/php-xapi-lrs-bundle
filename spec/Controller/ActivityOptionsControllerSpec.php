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

/**
 * @author Mathieu Boldo <mathieu.boldo@entrili.com>
 */
class ActivityOptionsControllerSpec extends ObjectBehavior
{
    public function it_should_throws_a_BadRequestHttpException_if_an_activityid_is_not_part_of_a_get_request(): void
    {
        $request = new Request();

        $this
            ->shouldThrow(BadRequestHttpException::class)
            ->during('optionsActivity', [$request]);
    }

    public function it_should_throws_a_BadRequestHttpException_if_an_activityid_is_not_valid_IRI(): void
    {
        $request = new Request();
        $request->query->set('activityId', []);

        $this
            ->shouldThrow(BadRequestHttpException::class)
            ->during('optionsActivity', [$request]);
    }

}
