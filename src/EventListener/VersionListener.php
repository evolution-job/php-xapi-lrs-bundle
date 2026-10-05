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

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use XApi\LrsBundle\App\XapiHeader;
use XApi\LrsBundle\App\XapiVersion;
use XApi\LrsBundle\Exception\BadRequestHttpException;

/**
 * @author Jérôme Parmentier <jerome.parmentier@acensi.fr>
 * @author Mathieu Boldo <mathieu.boldo@entrili.com>
 */
final readonly class VersionListener
{
    public function __construct(private XapiRequestMatcher $xapiRequestMatcher) { }

    public function onKernelRequest(RequestEvent $requestEvent): void
    {
        if (!$this->xapiRequestMatcher->matches($requestEvent)) {
            return;
        }

        $request = $requestEvent->getRequest();

        if ($request->isMethod(Request::METHOD_OPTIONS)) {
            return;
        }

        if (null === $version = $request->headers->get(XapiHeader::VERSION)) {
            throw new BadRequestHttpException(sprintf('Missing required "%s" header.', XapiHeader::VERSION));
        }

        if (preg_match('/^1\.0(?:\.\d+)?$/', (string)$version)) {
            if ('1.0' === $version) {
                $request->headers->set(XapiHeader::VERSION, XapiVersion::V1_0_0);
            }

            return;
        }

        throw new BadRequestHttpException(sprintf('xAPI version "%s" is not supported.', $version));
    }
}
