<?php

namespace XApi\LrsBundle\EventListener;

use Symfony\Component\HttpKernel\Event\KernelEvent;
use XApi\LrsBundle\App\XapiAttribute;

final class XapiRequestMatcher
{
    public function matches(KernelEvent $event): bool
    {
        if (!$event->isMainRequest()) {
            return false;
        }

        $request = $event->getRequest();

        if (!$request->attributes->has(XapiAttribute::LRS_ROUTE)) {
            return false;
        }

        if (!str_starts_with(
                $request->attributes->get('_route', ''),
                XapiAttribute::ROUTE_PREFIX,
            )
        ) {
            return false;
        }

        return true;
    }
}