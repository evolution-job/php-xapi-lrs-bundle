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
use Symfony\Component\HttpFoundation\Response;


/**
 * @author Mathieu Boldo <mathieu.boldo@entrili.com>
 */
final class StateOptionsController
{
    public function optionsState(): JsonResponse
    {
        $headers = [
            'Allow'                        => 'GET, HEAD, POST, PUT, DELETE',
            'Access-Control-Allow-Methods' => 'GET, HEAD, POST, PUT, DELETE, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Experience-API-Version, X-Experience-API-Consistent-Through',
        ];

        return new JsonResponse(null, Response::HTTP_NO_CONTENT, $headers);
    }
}
