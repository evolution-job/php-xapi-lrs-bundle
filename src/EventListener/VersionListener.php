<?php

/*
 * This file is part of the xAPI package.
 *
 * (c) Christian Flothmann <christian.flothmann@xabbuh.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace XApi\LrsBundle\EventListener;

use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * @author Jérôme Parmentier <jerome.parmentier@acensi.fr>
 */
class VersionListener
{
    public const string XAPI_HEADER = 'X-Experience-API-Version';
    public const string XAPI_VERSION_1_0_0 = '1.0.0';
    public const string XAPI_VERSION_1_0_3 = '1.0.3';

    public function onKernelRequest(RequestEvent $requestEvent): void
    {
        if (!$requestEvent->isMainRequest()) {
            return;
        }

        $request = $requestEvent->getRequest();

        if (!$request->attributes->has('xapi_lrs.route')) {
            return;
        }

        if (null === $version = $request->headers->get(self::XAPI_HEADER)) {
            throw new BadRequestHttpException('Missing required "X-Experience-API-Version" header.');
        }

        if (preg_match('/^1\.0(?:\.\d+)?$/', (string)$version)) {
            if ('1.0' === $version) {
                $request->headers->set(self::XAPI_HEADER, self::XAPI_VERSION_1_0_0);
            }

            return;
        }

        throw new BadRequestHttpException(sprintf('xAPI version "%s" is not supported.', $version));
    }

    public function onKernelResponse(ResponseEvent $responseEvent): void
    {
        if (!$responseEvent->isMainRequest()) {
            return;
        }

        if (!$responseEvent->getRequest()->attributes->has('xapi_lrs.route')) {
            return;
        }

        $headers = $responseEvent->getResponse()->headers;

        if (!$headers->has(self::XAPI_HEADER)) {
            $headers->set(self::XAPI_HEADER, self::XAPI_VERSION_1_0_3);
        }
    }
}
