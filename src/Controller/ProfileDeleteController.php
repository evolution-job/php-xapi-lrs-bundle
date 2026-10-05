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
use Xabbuh\XApi\Common\Exception\BadRequestException;
use XApi\LrsBundle\Response\JsonResponse;
use XApi\LrsBundle\Service\ProfileService;

/**
 * @author Mathieu Boldo <mathieu.boldo@entrili.com>
 */
final readonly class ProfileDeleteController
{
    public function __construct(private ProfileService $profileService) { }

    /** @throws BadRequestException */
    public function deleteActivityProfile(Request $request): JsonResponse
    {
        return $this->profileService->deleteActivityProfile($request);
    }

    /** @throws BadRequestException */
    public function deleteAgentProfile(Request $request): JsonResponse
    {
        return $this->profileService->deleteAgentProfile($request);
    }
}
