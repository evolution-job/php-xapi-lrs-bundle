<?php

namespace spec\XApi\LrsBundle\EventListener;

use PhpSpec\ObjectBehavior;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Xabbuh\XApi\Common\Exception\BadRequestException;
use XApi\LrsBundle\EventListener\XapiRequestMatcher;

/**
 * @author Jérôme Parmentier <jerome.parmentier@acensi.fr>
 * @author Mathieu Boldo <mathieu.boldo@entrili.com>
 */
class VersionListenerSpec extends ObjectBehavior
{
    public function let(RequestEvent $requestEvent, Request $request, ParameterBag $parameterBag, HeaderBag $headerBag): void
    {
        $parameterBag->has('xapi_lrs.route')->willReturn(true);
        $parameterBag->get('_route', '')->willReturn('xapi_lrs.route');

        $request->attributes = $parameterBag;
        $request->headers = $headerBag;

        $requestEvent->isMainRequest()->willReturn(true);
        $requestEvent->getRequest()->willReturn($request);

        $xapiRequestMatcher = new XapiRequestMatcher();
        $this->beConstructedWith($xapiRequestMatcher, ['https://learning.repository.example.com']);
    }

    public function it_returns_null_if_requests_are_not_main(HttpKernelInterface $httpKernel, RequestEvent $requestEvent, Request $request, Response $response): void
    {
        $requestEvent->isMainRequest()->willReturn(false);
        $requestEvent->getRequest()->shouldNotBeCalled();

        $this->onKernelRequest($requestEvent)->shouldReturn(null);
    }

    public function it_returns_null_if_not_xapi_route(HttpKernelInterface $httpKernel, RequestEvent $requestEvent, Request $request, ParameterBag $parameterBag, Response $response): void
    {
        $parameterBag->has('xapi_lrs.route')->shouldBeCalled()->willReturn(false);

        $request->attributes = $parameterBag;

        $request->isMethod(Request::METHOD_OPTIONS)->willReturn(false);

        $this->onKernelRequest($requestEvent)->shouldReturn(null);
    }

    public function it_throws_a_BadRequestException_if_no_X_Experience_API_Version_header_is_set(RequestEvent $requestEvent, Request $request, HeaderBag $headerBag): void
    {
        $headerBag->get('X-Experience-API-Version')->shouldBeCalled()->willReturn(null);
        $request->isMethod(Request::METHOD_OPTIONS)->willReturn(false);
        $this
            ->shouldThrow(new BadRequestException('Missing required "X-Experience-API-Version" header.'))
            ->during('onKernelRequest', [$requestEvent]);
    }

    public function it_throws_a_BadRequestException_if_specified_version_is_not_supported(RequestEvent $requestEvent, Request $request, HeaderBag $headerBag): void
    {
        $headerBag->get('X-Experience-API-Version')->shouldBeCalled()->willReturn('0.9.5');
        $request->isMethod(Request::METHOD_OPTIONS)->willReturn(false);

        $this
            ->shouldThrow(new BadRequestException('xAPI version "0.9.5" is not supported.'))
            ->during('onKernelRequest', [$requestEvent]);

        $headerBag->get('X-Experience-API-Version')->shouldBeCalled()->willReturn('1.1.0');

        $this
            ->shouldThrow(new BadRequestException('xAPI version "1.1.0" is not supported.'))
            ->during('onKernelRequest', [$requestEvent]);
    }

    public function it_normalizes_the_X_Experience_API_Version_header(RequestEvent $requestEvent, Request $request, HeaderBag $headerBag): void
    {
        $request->isMethod(Request::METHOD_OPTIONS)->willReturn(false);
        $headerBag->get('X-Experience-API-Version')->shouldBeCalled()->willReturn('1.0');
        $headerBag->set('X-Experience-API-Version', '1.0.0')->shouldBeCalled();

        $this->onKernelRequest($requestEvent);
    }

    public function it_returns_null_if_version_is_supported(RequestEvent $requestEvent, Request $request, HeaderBag $headerBag): void
    {
        $request->isMethod(Request::METHOD_OPTIONS)->willReturn(false);
        $headerBag->get('X-Experience-API-Version')->shouldBeCalled()->willReturn('1.0.0');

        $this->onKernelRequest($requestEvent)->shouldReturn(null);
    }
}
