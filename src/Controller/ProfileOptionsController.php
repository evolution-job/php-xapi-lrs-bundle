<?php

declare(strict_types=1);

/*
 * This file is part of the xAPI package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace XApi\LrsBundle\Controller;

use Symfony\Component\HttpFoundation\Response;
use XApi\LrsBundle\Response\JsonResponse;

/**
 * @author Mathieu Boldo <mathieu.boldo@entrili.com>
 */
final class ProfileOptionsController
{
    public function optionsProfiles(): JsonResponse
    {
        return new JsonResponse(null, Response::HTTP_NO_CONTENT, [
            'Allow' => 'GET, HEAD, POST, PUT, DELETE',
            'Access-Control-Allow-Methods' => 'GET, HEAD, POST, PUT, DELETE, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Experience-API-Version, X-Experience-API-Consistent-Through',
        ]);
    }
}
