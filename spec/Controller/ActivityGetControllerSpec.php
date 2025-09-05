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
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Xabbuh\XApi\Common\Exception\NotFoundException;
use Xabbuh\XApi\DataFixtures\ActivityFixtures;
use Xabbuh\XApi\Model\IRI;
use Xabbuh\XApi\Serializer\ActivitySerializerInterface;
use XApi\Fixtures\Json\ActivityJsonFixtures;
use XApi\LrsBundle\Response\XapiJsonResponse;
use XApi\Repository\Api\ActivityRepositoryInterface;

/**
 * @author Mathieu Boldo <mathieu.boldo@entrili.com>
 */
class ActivityGetControllerSpec extends ObjectBehavior
{
    public function let(ActivityRepositoryInterface $activityRepository, ActivitySerializerInterface $activitySerializer): void
    {
        $this->beConstructedWith($activityRepository, $activitySerializer);
    }

    public function it_should_throws_a_BadRequestHttpException_if_an_activityid_is_not_part_of_a_get_request(): void
    {
        $request = new Request();

        $this
            ->shouldThrow(BadRequestHttpException::class)
            ->during('getActivity', [$request]);
    }

    public function it_should_throws_a_NotFoundHttpException_if_no_activity_matches_activityid(ActivityRepositoryInterface $activityRepository): void
    {
        $activityId = 'http://tincanapi.com/conformancetest/activityid';

        $request = new Request();
        $request->query->set('activityId', $activityId);

        $activityRepository->findActivityById(IRI::fromString($activityId))->shouldBeCalled()->willThrow(new NotFoundException(''));

        $this
            ->shouldThrow(NotFoundHttpException::class)
            ->during('getActivity', [$request]);
    }

    public function it_should_returns_a_XapiJsonResponse(ActivityRepositoryInterface $activityRepository, ActivitySerializerInterface $activitySerializer): void
    {
        $activityId = 'http://tincanapi.com/conformancetest/activityid';
        $activity = ActivityFixtures::getTypicalActivity();

        $request = new Request();
        $request->query->set('activityId', $activityId);

        $activityRepository->findActivityById(IRI::fromString($activityId))->shouldBeCalled()->willReturn($activity);
        $activitySerializer->serializeActivity($activity)->shouldBeCalled()->willReturn(ActivityJsonFixtures::getTypicalActivity());

        $this->getActivity($request)->shouldReturnAnInstanceOf(XapiJsonResponse::class);
    }
}
