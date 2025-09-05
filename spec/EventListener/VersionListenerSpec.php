<?php

namespace spec\XApi\LrsBundle\EventListener;

use PhpSpec\ObjectBehavior;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class VersionListenerSpec extends ObjectBehavior
{
    public function let(RequestEvent $requestEvent, Request $request, ParameterBag $parameterBag, HeaderBag $headerBag): void
    {
        $parameterBag->has('xapi_lrs.route')->willReturn(true);

        $request->attributes = $parameterBag;
        $request->headers = $headerBag;

        $requestEvent->isMainRequest()->willReturn(true);
        $requestEvent->getRequest()->willReturn($request);
    }

    public function it_returns_null_if_requests_are_not_main(HttpKernelInterface $kernel, RequestEvent $requestEvent, Request $request, Response $response): void
    {
        $requestEvent->isMainRequest()->willReturn(false);
        $requestEvent->getRequest()->shouldNotBeCalled();

        $this->onKernelRequest($requestEvent)->shouldReturn(null);

        $responseEvent = new ResponseEvent(
            $kernel->getWrappedObject(),
            $request->getWrappedObject(),
            HttpKernelInterface::SUB_REQUEST,
            $response->getWrappedObject()
        );

        $this->onKernelResponse($responseEvent)->shouldReturn(null);
    }

    public function it_returns_null_if_not_xapi_route(HttpKernelInterface $kernel, RequestEvent $requestEvent, Request $request, ParameterBag $parameterBag, Response $response): void
    {
        $parameterBag->has('xapi_lrs.route')->shouldBeCalled()->willReturn(false);

        $request->attributes = $parameterBag;

        $this->onKernelRequest($requestEvent)->shouldReturn(null);

        $responseEvent = new ResponseEvent(
            $kernel->getWrappedObject(),
            $request->getWrappedObject(),
            HttpKernelInterface::MAIN_REQUEST,
            $response->getWrappedObject()
        );

        $this->onKernelResponse($responseEvent)->shouldReturn(null);
    }

    public function it_throws_a_BadRequestHttpException_if_no_X_Experience_API_Version_header_is_set(RequestEvent $requestEvent, HeaderBag $headerBag): void
    {
        $headerBag->get('X-Experience-API-Version')->shouldBeCalled()->willReturn(null);
        $this
            ->shouldThrow(new BadRequestHttpException('Missing required "X-Experience-API-Version" header.'))
            ->during('onKernelRequest', [$requestEvent]);

    }

    public function it_throws_a_BadRequestHttpException_if_specified_version_is_not_supported(RequestEvent $requestEvent, HeaderBag $headerBag): void
    {
        $headerBag->get('X-Experience-API-Version')->shouldBeCalled()->willReturn('0.9.5');

        $this
            ->shouldThrow(new BadRequestHttpException('xAPI version "0.9.5" is not supported.'))
            ->during('onKernelRequest', [$requestEvent]);

        $headerBag->get('X-Experience-API-Version')->shouldBeCalled()->willReturn('1.1.0');

        $this
            ->shouldThrow(new BadRequestHttpException('xAPI version "1.1.0" is not supported.'))
            ->during('onKernelRequest', [$requestEvent]);
    }

    public function it_normalizes_the_X_Experience_API_Version_header(RequestEvent $requestEvent, HeaderBag $headerBag): void
    {
        $headerBag->get('X-Experience-API-Version')->shouldBeCalled()->willReturn('1.0');
        $headerBag->set('X-Experience-API-Version', '1.0.0')->shouldBeCalled();

        $this->onKernelRequest($requestEvent);
    }

    public function it_returns_null_if_version_is_supported(RequestEvent $requestEvent, HeaderBag $headerBag): void
    {
        $headerBag->get('X-Experience-API-Version')->shouldBeCalled()->willReturn('1.0.0');

        $this->onKernelRequest($requestEvent)->shouldReturn(null);
    }

    public function it_sets_a_X_Experience_API_Version_header_in_response(HttpKernelInterface $kernel, Request $request, Response $response, ResponseHeaderBag $responseHeaderBag): void
    {
        $responseHeaderBag->has('X-Experience-API-Version')->shouldBeCalled()->willReturn(false);
        $responseHeaderBag->set('X-Experience-API-Version', '1.0.3')->shouldBeCalled();
        $response->headers = $responseHeaderBag;

        $responseEvent = new ResponseEvent(
            $kernel->getWrappedObject(),
            $request->getWrappedObject(),
            HttpKernelInterface::MAIN_REQUEST,
            $response->getWrappedObject()
        );

        $this->onKernelResponse($responseEvent)->shouldReturn(null);
    }
}
