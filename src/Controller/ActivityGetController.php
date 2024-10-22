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

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Xabbuh\XApi\Common\Exception\NotFoundException;
use Xabbuh\XApi\Model\IRI;
use Xabbuh\XApi\Serializer\ActivitySerializerInterface;
use XApi\Repository\Api\ActivityRepositoryInterface;

/**
 * @author Jérôme Parmentier <jerome.parmentier@acensi.fr>
 */
final class ActivityGetController
{

    public function __construct(
        private readonly ActivityRepositoryInterface $activityRepository,
        private readonly ActivitySerializerInterface $activitySerializer
    ) {}

    public function getActivity(Request $request): JsonResponse
    {
        if (null === $activityId = $request->query->get('activityId')) {
            throw new BadRequestHttpException('Required activityId parameter is missing.');
        }

        try {
            $activity = $this->activityRepository->findActivityById(IRI::fromString($activityId));
        } catch (NotFoundException $notFoundException) {
            throw new NotFoundHttpException(sprintf('No activity matching the following id "%s" has been found.', $activityId), $notFoundException);
        }

        return new JsonResponse($this->activitySerializer->serializeActivity($activity), 200, [], true);
    }
}
