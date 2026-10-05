<?php

declare(strict_types=1);

/*
 * This file is part of the xAPI package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace XApi\LrsBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Xabbuh\XApi\Common\Exception\BadRequestException;
use Xabbuh\XApi\Common\Exception\NotFoundException;
use Xabbuh\XApi\Model\Activity;
use Xabbuh\XApi\Model\IRI;
use Xabbuh\XApi\Serializer\ActivitySerializerInterface;
use XApi\LrsBundle\App\IriValidator;
use XApi\LrsBundle\Response\JsonResponse;
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

    public function getActivities(Request $request): JsonResponse
    {
        $parameters = $request->query->all();
        $unknownParameters = array_diff(array_keys($parameters), ['activityId']);

        if ([] !== $unknownParameters) {
            throw new BadRequestException(sprintf(
                'Unrecognized query parameter(s): %s.',
                implode(', ', $unknownParameters)
            ));
        }

        if (!array_key_exists('activityId', $parameters)) {
            throw new BadRequestException('Required activityId parameter is missing.');
        }

        $activityId = $parameters['activityId'];

        if (!is_string($activityId)) {
            throw new BadRequestException('Required activityId parameter is not a string.');
        }

        if (!IriValidator::isValid($activityId)) {
            throw new BadRequestException(sprintf('Parameter activityId ("%s") is not a valid IRI.', $activityId));
        }

        $iri = IRI::fromString($activityId);

        try {
            $activity = $this->activityRepository->findActivityById($iri);
        } catch (NotFoundException) {
            $activity = null;
        }

        // An unknown Activity ID is still a valid resource lookup: return the
        // minimal Activity containing that ID rather than a 404.
        // https://github.com/adlnet/xAPI-Spec/blob/master/xAPI-Communication.md
        $activity ??= new Activity($iri);

        return new JsonResponse(
            $this->activitySerializer->serializeActivity($activity),
            Response::HTTP_OK,
            [],
            true,
            $request->isMethod(Request::METHOD_HEAD)
        );
    }
}
