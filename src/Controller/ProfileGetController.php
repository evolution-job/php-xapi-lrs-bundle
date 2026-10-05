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
use XApi\LrsBundle\Response\StateDocumentResponse;
use XApi\LrsBundle\Service\ProfileService;

/**
 * @author Mathieu Boldo <mathieu.boldo@entrili.com>
 */
final readonly class ProfileGetController
{
    public function __construct(private ProfileService $profileService) { }

    /** @throws BadRequestException|JsonException */
    public function getActivityProfile(Request $request): JsonResponse|StateDocumentResponse
    {
        return $this->profileService->getActivityProfile($request);
    }

    /** @throws BadRequestException|JsonException */
    public function getAgentProfile(Request $request): JsonResponse|StateDocumentResponse
    {
        return $this->profileService->getAgentProfile($request);
    }
}
