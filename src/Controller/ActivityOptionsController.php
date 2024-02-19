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

use Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Xabbuh\XApi\Model\IRI;

final class ActivityOptionsController
{
    /**
     * @param Request $request
     * @return Response
     */
    public function optionsActivity(Request $request): Response
    {
        if (null === $activityId = $request->query->get('activityId')) {
            throw new BadRequestHttpException('Required stateId parameter is missing.');
        }

        try {
            IRI::fromString($activityId);
        } catch (Exception $e) {
            throw new BadRequestHttpException(sprintf('Parameter activityId ("%s") is not a valid IRI.', $activityId), $e);
        }

        return new Response('', 204);
    }
}
