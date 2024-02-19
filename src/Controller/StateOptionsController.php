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

final class StateOptionsController
{
    /**
     * @param Request $request
     * @return Response
     */
    public function optionsState(Request $request): Response
    {
        if (null === $request->query->get('stateId')) {
            throw new BadRequestHttpException('Required stateId parameter is missing.');
        }

        return new Response('', 204);
    }
}
