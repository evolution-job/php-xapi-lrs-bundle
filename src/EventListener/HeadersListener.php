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
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use XApi\LrsBundle\App\XapiHeader;
use XApi\LrsBundle\App\XapiVersion;

/**
 * @author Mathieu Boldo <mathieu.boldo@entrili.com>
 */
final readonly class HeadersListener
{
    private const string ALLOWED_METHODS = 'GET, POST, PUT, DELETE, HEAD, OPTIONS';

    /**
     * @param string[] $allowedOrigins
     */
    public function __construct(
        private XapiRequestMatcher $xapiRequestMatcher,
        private array $allowedOrigins
    ) { }

    public function onKernelResponse(ResponseEvent $responseEvent): void
    {
        if (!$this->xapiRequestMatcher->matches($responseEvent)) {
            return;
        }

        $headersToAdd = [
            'Access-Control-Allow-Credentials' => 'true',
            'Access-Control-Expose-Headers'    => 'ETag, Last-Modified, X-Experience-API-Version, X-Experience-API-Consistent-Through',
        ];

        // HOST
        $request = $responseEvent->getRequest();
        $host = $this->resolveHost($request);
        if (null !== $host) {
            $headersToAdd['Access-Control-Allow-Origin'] = $host;
            $headersToAdd['Content-Security-Policy'] = sprintf('frame-ancestors %s', $host);
        }

        // OPTIONS
        if ($request->isMethod(Request::METHOD_OPTIONS)) {
            $headersToAdd = array_merge($headersToAdd, [
                'Allow'                        => self::ALLOWED_METHODS,
                'Access-Control-Allow-Headers' => 'Accept, Authorization, Content-Type, If-Match, If-None-Match, X-Experience-API-Version',
                'Access-Control-Allow-Methods' => self::ALLOWED_METHODS,
                'Access-Control-Max-Age'       => '86400',
            ]);
        }

        // Add HEADERS
        $headers = $responseEvent->getResponse()->headers;
        $headers->set('Vary', 'Origin', false);
        $headers->set(XapiHeader::VERSION, XapiVersion::V1_0_3, false);
        foreach ($headersToAdd as $header => $value) {
            $headers->set($header, $value);
        }
    }

    private function resolveHost(Request $request): ?string
    {
        $origin = $request->headers->get('Origin');
        if (null === $origin) {
            return null;
        }

        return in_array($origin, $this->allowedOrigins, true)
            ? $origin
            : null;
    }
}
