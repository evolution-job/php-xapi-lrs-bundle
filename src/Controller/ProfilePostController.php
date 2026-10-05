<?php

declare(strict_types=1);

/*
 * This file is part of the xAPI package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace XApi\LrsBundle\Controller;

use JsonException;
use Symfony\Component\HttpFoundation\Request;
use Xabbuh\XApi\Common\Exception\BadRequestException;
use XApi\LrsBundle\Response\JsonResponse;
use XApi\LrsBundle\Service\ProfileService;

/**
 * @author Mathieu Boldo <mathieu.boldo@entrili.com>
 */
final readonly class ProfilePostController
{
    public function __construct(private ProfileService $profileService) { }

    /** @throws BadRequestException|JsonException */
    public function postActivityProfile(Request $request): JsonResponse
    {
        return $this->profileService->postActivityProfile($request);
    }

    /** @throws BadRequestException|JsonException */
    public function postAgentProfile(Request $request): JsonResponse
    {
        return $this->profileService->postAgentProfile($request);
    }
}
