<?php

namespace spec\XApi\LrsBundle\EventListener;

use PhpSpec\ObjectBehavior;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class VersionListenerSpec extends ObjectBehavior
{
    public function let(GetResponseEvent $getResponseEvent, FilterResponseEvent $filterResponseEvent, Request $request, ParameterBag $parameterBag, HeaderBag $headerBag): void
    {
        $parameterBag->has('xapi_lrs.route')->willReturn(true);

        $request->attributes = $parameterBag;
        $request->headers = $headerBag;

        $getResponseEvent->isMasterRequest()->willReturn(true);
        $getResponseEvent->getRequest()->willReturn($request);

        $filterResponseEvent->isMasterRequest()->willReturn(true);
        $filterResponseEvent->getRequest()->willReturn($request);
    }

    public function it_returns_null_if_requests_are_not_master(GetResponseEvent $getResponseEvent, FilterResponseEvent $filterResponseEvent): void
    {
        $getResponseEvent->isMasterRequest()->willReturn(false);
        $getResponseEvent->getRequest()->shouldNotBeCalled();

        $this->onKernelRequest($getResponseEvent)->shouldReturn(null);

        $filterResponseEvent->isMasterRequest()->willReturn(false);
        $filterResponseEvent->getRequest()->shouldNotBeCalled();

        $this->onKernelResponse($filterResponseEvent)->shouldReturn(null);
    }

    public function it_returns_null_if_not_xapi_route(GetResponseEvent $getResponseEvent, FilterResponseEvent $filterResponseEvent, ParameterBag $parameterBag): void
    {
        $parameterBag->has('xapi_lrs.route')->shouldBeCalled()->willReturn(false);

        $this->onKernelRequest($getResponseEvent)->shouldReturn(null);

        $this->onKernelResponse($filterResponseEvent)->shouldReturn(null);
    }

    public function it_throws_a_badrequesthttpexception_if_no_X_Experience_API_Version_header_is_set(GetResponseEvent $getResponseEvent, HeaderBag $headerBag): void
    {
        $headerBag->get('X-Experience-API-Version')->shouldBeCalled()->willReturn(null);

        $this
            ->shouldThrow(new BadRequestHttpException('Missing required "X-Experience-API-Version" header.'))
            ->during('onKernelRequest', [$getResponseEvent]);
    }

    public function it_throws_a_badrequesthttpexception_if_specified_version_is_not_supported(GetResponseEvent $getResponseEvent, HeaderBag $headerBag): void
    {
        $headerBag->get('X-Experience-API-Version')->shouldBeCalled()->willReturn('0.9.5');

        $this
            ->shouldThrow(new BadRequestHttpException('xAPI version "0.9.5" is not supported.'))
            ->during('onKernelRequest', [$getResponseEvent]);

        $headerBag->get('X-Experience-API-Version')->shouldBeCalled()->willReturn('1.1.0');

        $this
            ->shouldThrow(new BadRequestHttpException('xAPI version "1.1.0" is not supported.'))
            ->during('onKernelRequest', [$getResponseEvent]);
    }

    public function it_normalizes_the_X_Experience_API_Version_header(GetResponseEvent $getResponseEvent, HeaderBag $headerBag): void
    {
        $headerBag->get('X-Experience-API-Version')->shouldBeCalled()->willReturn('1.0');
        $headerBag->set('X-Experience-API-Version', '1.0.0')->shouldBeCalled();

        $this->onKernelRequest($getResponseEvent);
    }

    public function it_returns_null_if_version_is_supported(GetResponseEvent $getResponseEvent, HeaderBag $headerBag): void
    {
        $headerBag->get('X-Experience-API-Version')->shouldBeCalled()->willReturn('1.0.0');

        $this->onKernelRequest($getResponseEvent)->shouldReturn(null);
    }

    public function it_sets_a_X_Experience_API_Version_header_in_response(FilterResponseEvent $filterResponseEvent, Response $response, HeaderBag $headerBag): void
    {
        $headerBag->has('X-Experience-API-Version')->shouldBeCalled()->willReturn(false);
        $headerBag->set('X-Experience-API-Version', '1.0.3')->shouldBeCalled();

        $response->headers = $headerBag;

        $filterResponseEvent->getResponse()->shouldBeCalled()->willReturn($response);

        $this->onKernelResponse($filterResponseEvent);
    }
}
