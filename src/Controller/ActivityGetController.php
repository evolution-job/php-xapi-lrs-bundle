<?php

/*
 * This file is part of the xAPI package.
 *
 * (c) Christian Flothmann <christian.flothmann@xabbuh.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace XApi\LrsBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Xabbuh\XApi\Common\Exception\NotFoundException;
use Xabbuh\XApi\Model\IRI;
use Xabbuh\XApi\Serializer\ActivitySerializerInterface;
use XApi\LrsBundle\Response\XapiJsonResponse;
use XApi\Repository\Api\ActivityRepositoryInterface;

/**
 * @author Mathieu Boldo <mathieu.boldo@entrili.com>
 */
final readonly class ActivityGetController
{
    public function __construct(
        private ActivityRepositoryInterface $activityRepository,
        private ActivitySerializerInterface $activitySerializer
    ) {}

    public function getActivity(Request $request): XapiJsonResponse
    {
        if (!$activityId = $request->query->all()['activityId'] ?? null) {
            throw new BadRequestHttpException('Required activityId parameter is missing.');
        }

        if (!is_string($activityId)) {
            throw new BadRequestHttpException('Required activityId parameter is not a string.');
        }

        try {
            $activity = $this->activityRepository->findActivityById(IRI::fromString($activityId));

            return new XapiJsonResponse($this->activitySerializer->serializeActivity($activity), Response::HTTP_OK, [], true);

        } catch (NotFoundException $notFoundException) {
            throw new NotFoundHttpException(sprintf('No activity matching the following id "%s" has been found.', $activityId), $notFoundException);
        }
    }
}
