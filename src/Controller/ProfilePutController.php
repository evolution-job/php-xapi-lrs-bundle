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
final readonly class ProfilePutController
{
    public function __construct(private ProfileService $profileService) { }

    /** @throws BadRequestException|JsonException */
    public function putActivityProfile(Request $request): JsonResponse
    {
        return $this->profileService->putActivityProfile($request);
    }

    /** @throws BadRequestException|JsonException */
    public function putAgentProfile(Request $request): JsonResponse
    {
        return $this->profileService->putAgentProfile($request);
    }
}
