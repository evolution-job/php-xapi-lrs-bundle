<?php

declare(strict_types=1);

/*
 * This file is part of the xAPI package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace XApi\LrsBundle\EventListener;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use XApi\LrsBundle\Service\RequestDeserializer;
use XApi\LrsBundle\Service\RequestMatcher;

/**
 * @author Christian Flothmann <christian.flothmann@xabbuh.de>
 * @author Mathieu Boldo <mathieu.boldo@entrili.com>
 */
final readonly class SerializerListener
{
    public function __construct(
        private RequestDeserializer $requestDeserializer,
        private RequestMatcher $requestMatcher
    ) { }

    public function onKernelRequest(RequestEvent $requestEvent): void
    {
        if (!$this->requestMatcher->matches($requestEvent)) {
            return;
        }

        $request = $requestEvent->getRequest();

        if ($request->isMethod(Request::METHOD_OPTIONS)) {
            return;
        }

        switch ($request->attributes->get('xapi_serializer')) {
            case 'state':
                $request->attributes->set('state', $this->requestDeserializer->deserializeState($request));
                break;

            case 'statement':
                $data = $this->requestDeserializer->deserializeStatement($request);

                if (is_array($data)) {
                    $request->attributes->set('statements', $data);

                    // Redirect to the right method in the Controller
                    $controller = $request->attributes->get('_controller');
                    if (is_string($controller) && str_ends_with($controller, '::postStatement')) {
                        $request->attributes->set('_controller', str_replace('::postStatement', '::postStatements', $controller));
                    }
                } else {
                    $request->attributes->set('statement', $data);
                }
                
                break;
        }
    }
}
