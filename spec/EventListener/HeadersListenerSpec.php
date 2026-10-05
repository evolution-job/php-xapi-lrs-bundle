<?php

namespace spec\XApi\LrsBundle\EventListener;

use PhpSpec\ObjectBehavior;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use XApi\LrsBundle\EventListener\XapiRequestMatcher;

/**
 * @author Mathieu Boldo <mathieu.boldo@entrili.com>
 */
class HeadersListenerSpec extends ObjectBehavior
{
    public function let(Request $request, ParameterBag $parameterBag, HeaderBag $headerBag): void
    {
        $parameterBag->has('xapi_lrs.route')->willReturn(true);
        $parameterBag->get('_route', '')->willReturn('xapi_lrs.route');
        $request->attributes = $parameterBag;

        $headerBag->get('Origin')->willReturn('https://learning.repository.example.com');
        $request->headers = $headerBag;

        $xapiRequestMatcher = new XapiRequestMatcher();
        $this->beConstructedWith($xapiRequestMatcher, ['https://learning.repository.example.com']);
    }

    public function it_returns_null_if_requests_are_not_main(HttpKernelInterface $httpKernel, Request $request, Response $response): void
    {
        $responseEvent = new ResponseEvent(
            $httpKernel->getWrappedObject(),
            $request->getWrappedObject(),
            HttpKernelInterface::SUB_REQUEST,
            $response->getWrappedObject()
        );

        $this->onKernelResponse($responseEvent)->shouldReturn(null);
    }

    public function it_returns_null_if_not_xapi_route(HttpKernelInterface $httpKernel, Request $request, ParameterBag $parameterBag, Response $response): void
    {
        $parameterBag->has('xapi_lrs.route')->shouldBeCalled()->willReturn(false);
        $request->attributes = $parameterBag;
        $request->isMethod(Request::METHOD_OPTIONS)->willReturn(false);

        $responseEvent = new ResponseEvent(
            $httpKernel->getWrappedObject(),
            $request->getWrappedObject(),
            HttpKernelInterface::MAIN_REQUEST,
            $response->getWrappedObject()
        );

        $this->onKernelResponse($responseEvent)->shouldReturn(null);
    }

    public function it_sets_headers_in_response_for_options_request(HttpKernelInterface $httpKernel, Request $request, Response $response, ResponseHeaderBag $responseHeaderBag): void
    {
        $request->isMethod(Request::METHOD_OPTIONS)->willReturn(true);

        $responseHeaderBag->set('Access-Control-Allow-Origin', 'https://learning.repository.example.com')->shouldBeCalled();
        $responseHeaderBag->set('Content-Security-Policy', 'frame-ancestors https://learning.repository.example.com')->shouldBeCalled();
        $responseHeaderBag->set('Access-Control-Max-Age', '86400')->shouldBeCalled();
        $responseHeaderBag->set('Allow', 'GET, POST, PUT, DELETE, HEAD, OPTIONS')->shouldBeCalled();
        $responseHeaderBag->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, HEAD, OPTIONS')->shouldBeCalled();
        $responseHeaderBag->set('Access-Control-Allow-Headers', 'Accept, Authorization, Content-Type, If-Match, If-None-Match, X-Experience-API-Version')->shouldBeCalled();
        $responseHeaderBag->set('Access-Control-Expose-Headers', 'ETag, Last-Modified, X-Experience-API-Version, X-Experience-API-Consistent-Through')->shouldBeCalled();
        $responseHeaderBag->set('Access-Control-Allow-Credentials', 'true')->shouldBeCalled();
        $responseHeaderBag->set('Vary', 'Origin', false)->shouldBeCalled();
        $responseHeaderBag->set('X-Experience-API-Version', '1.0.3', false)->shouldBeCalled();
        $response->headers = $responseHeaderBag;

        $responseEvent = new ResponseEvent(
            $httpKernel->getWrappedObject(),
            $request->getWrappedObject(),
            HttpKernelInterface::MAIN_REQUEST,
            $response->getWrappedObject()
        );

        $this->onKernelResponse($responseEvent)->shouldReturn(null);
    }

    public function it_sets_headers_in_response_for_not_options_request(HttpKernelInterface $httpKernel, Request $request, Response $response, ResponseHeaderBag $responseHeaderBag): void
    {
        $request->isMethod(Request::METHOD_OPTIONS)->willReturn(false);

        $responseHeaderBag->set('Access-Control-Allow-Origin', 'https://learning.repository.example.com')->shouldBeCalled();
        $responseHeaderBag->set('Content-Security-Policy', 'frame-ancestors https://learning.repository.example.com')->shouldBeCalled();
        $responseHeaderBag->set('Access-Control-Expose-Headers', 'ETag, Last-Modified, X-Experience-API-Version, X-Experience-API-Consistent-Through')->shouldBeCalled();
        $responseHeaderBag->set('Access-Control-Allow-Credentials', 'true')->shouldBeCalled();
        $responseHeaderBag->set('Vary', 'Origin', false)->shouldBeCalled();
        $responseHeaderBag->set('X-Experience-API-Version', '1.0.3', false)->shouldBeCalled();
        $response->headers = $responseHeaderBag;

        $responseEvent = new ResponseEvent(
            $httpKernel->getWrappedObject(),
            $request->getWrappedObject(),
            HttpKernelInterface::MAIN_REQUEST,
            $response->getWrappedObject()
        );

        $this->onKernelResponse($responseEvent)->shouldReturn(null);
    }
}
