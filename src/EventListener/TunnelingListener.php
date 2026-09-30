<?php

namespace XApi\LrsBundle\EventListener;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * Intercep the request before routing to apply the POST Tunneling xAPI
 *
 * @author Mathieu Boldo <mathieu.boldo@entrili.com>
 */
final readonly class TunnelingListener
{
    public function onKernelRequest(RequestEvent $requestEvent): void
    {
        if (!$requestEvent->isMainRequest()) {
            return;
        }

        $request = $requestEvent->getRequest();

        if (false === $request->isMethod(Request::METHOD_POST)) {
            return;
        }

        // Search for the 'method' parameter in the query string
        $tunneledMethod = $request->query->get('method');

        if (null === $tunneledMethod) {
            return;
        }

        $tunneledMethod = strtoupper(trim($tunneledMethod));

        // xAPI allows primarily tunneling to PUT and DELETE
        if (in_array($tunneledMethod, [Request::METHOD_PUT, Request::METHOD_DELETE], true)) {
            // Override the HTTP method to correctly route the request
            $request->setMethod($tunneledMethod);
        }
    }
}
