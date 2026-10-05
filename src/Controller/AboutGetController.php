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
use XApi\LrsBundle\App\XapiVersion;
use XApi\LrsBundle\Response\JsonResponse;

/**
 * @author Mathieu Boldo <mathieu.boldo@entrili.com>
 */
final class AboutGetController
{
    public function getAbout(Request $request): JsonResponse
    {
        return new JsonResponse(['version' => [XapiVersion::V1_0_3]], isHeadRequest: $request->isMethod(Request::METHOD_HEAD));
    }
}
