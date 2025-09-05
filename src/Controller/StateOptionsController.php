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
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;


/**
 * @author Mathieu Boldo <mathieu.boldo@entrili.com>
 */
final class StateOptionsController
{
    public function optionsState(Request $request): JsonResponse
    {
        if (!$stateId = $request->query->all()['stateId'] ?? null) {
            throw new BadRequestHttpException('Required stateId parameter is missing.');
        }

        if (!is_string($stateId)) {
            throw new BadRequestHttpException('Required stateId parameter is not a string.');
        }

        return new JsonResponse('', Response::HTTP_NO_CONTENT);
    }
}
