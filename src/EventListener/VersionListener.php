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
    public function onKernelRequest(RequestEvent $requestEvent): void
    {
        if (!$requestEvent->isMainRequest()) {
            return;
        }

        $request = $requestEvent->getRequest();

        if (!$request->attributes->has('xapi_lrs.route')) {
            return;
        }

        if (null === $version = $request->headers->get('X-Experience-API-Version')) {
            $request->headers->set('X-Experience-API-Version', '1.0.0');
        }

        if (preg_match('/^1\.0(?:\.\d+)?$/', (string)$version)) {
            if ('1.0' === $version) {
                $request->headers->set('X-Experience-API-Version', '1.0.0');
            }

            return;
        }

        throw new BadRequestHttpException(sprintf('xAPI version "%s" is not supported.', $version));
    }

    public function onKernelResponse(ResponseEvent $filterResponseEvent): void
    {
        if (!$filterResponseEvent->isMainRequest()) {
            return;
        }

        if (!$filterResponseEvent->getRequest()->attributes->has('xapi_lrs.route')) {
            return;
        }

        $headers = $filterResponseEvent->getResponse()->headers;

        if (!$headers->has('X-Experience-API-Version')) {
            $headers->set('X-Experience-API-Version', '1.0.3');
        }
    }
}
