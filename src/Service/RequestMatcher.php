<?php

/*
 * This file is part of the xAPI package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace XApi\LrsBundle\Service;

use Symfony\Component\HttpKernel\Event\KernelEvent;
use XApi\LrsBundle\App\XapiAttribute;

/**
 * @author Mathieu Boldo <mathieu.boldo@entrili.com>
 */
final class RequestMatcher
{
    public function matches(KernelEvent $kernelEvent): bool
    {
        if (!$kernelEvent->isMainRequest()) {
            return false;
        }

        $request = $kernelEvent->getRequest();

        if (!$request->attributes->has(XapiAttribute::LRS_ROUTE)) {
            return false;
        }

        return str_starts_with(
                $request->attributes->get('_route', ''),
                XapiAttribute::ROUTE_PREFIX,
            );
    }
}