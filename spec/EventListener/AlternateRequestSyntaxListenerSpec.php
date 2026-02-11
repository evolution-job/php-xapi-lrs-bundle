<?php

namespace spec\XApi\LrsBundle\EventListener;

use PhpSpec\ObjectBehavior;
use Symfony\Component\HttpFoundation\FileBag;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ServerBag;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class AlternateRequestSyntaxListenerSpec extends ObjectBehavior
{
    public function let(RequestEvent $requestEvent, Request $request, ParameterBag $attributes, HeaderBag $headerBag): void
    {
        $attributes->has('xapi_lrs.route')->willReturn(true);

        $request->attributes = $attributes;
        $request->headers = $headerBag;
        $request->query = new InputBag(['method' => 'POST']);
        $request->request = new InputBag();
        $request->getMethod()->willReturn('POST');

        $requestEvent->isMainRequest()->willReturn(true);
        $requestEvent->getRequest()->willReturn($request);
    }

    public function it_returns_null_if_request_is_not_main(RequestEvent $requestEvent): void
    {
        $requestEvent->isMainRequest()->willReturn(false);
        $requestEvent->getRequest()->shouldNotBeCalled();

        $this->onKernelRequest($requestEvent)->shouldReturn(null);
    }

    public function it_returns_null_if_request_has_no_attribute_xapi_lrs_route(RequestEvent $requestEvent, Request $request, ParameterBag $parameterBag): void
    {
        $parameterBag->has('xapi_lrs.route')->shouldBeCalled()->willReturn(false);

        $request->attributes = $parameterBag;
        $requestEvent->getRequest()->willReturn($request);

        $this->onKernelRequest($requestEvent)->shouldReturn(null);
    }

    public function it_returns_null_if_request_method_is_get(RequestEvent $requestEvent, Request $request, ParameterBag $parameterBag): void
    {
        $parameterBag->get('method')->shouldNotBeCalled();
        $request->getMethod()->willReturn('GET');
        $request->isMethod(Request::METHOD_POST)->willReturn(false);

        $this->onKernelRequest($requestEvent)->shouldReturn(null);
    }

    public function it_returns_null_if_request_method_is_put(RequestEvent $requestEvent, Request $request, ParameterBag $parameterBag): void
    {
        $parameterBag->get('method')->shouldNotBeCalled();
        $request->getMethod()->willReturn('PUT');
        $request->isMethod(Request::METHOD_POST)->willReturn(false);

        $this->onKernelRequest($requestEvent)->shouldReturn(null);
    }

    public function it_throws_a_BadRequestHttpException_if_other_query_parameter_than_method_is_set(RequestEvent $requestEvent): void
    {
        $request = new Request(
            query: ['method' => 'POST', 'foo' => 'bar'],
            attributes: ['xapi_lrs.route' => true],
        );

        $requestEvent->getRequest()->willReturn($request);

        $this->onKernelRequest($requestEvent)->shouldThrow(BadRequestHttpException::class);
    }

    public function it_sets_the_request_method_equals_to_method_query_parameter(RequestEvent $requestEvent, Request $request, ParameterBag $parameterBag): void
    {
        $parameterBag->has('xapi_lrs.route')->shouldBeCalled()->willReturn(true);
        $request->isMethod(Request::METHOD_POST)->willReturn(true);

        $request->attributes = $parameterBag;

        $query = new InputBag(['method' => 'POST']);
        $request->setMethod('POST')->shouldBeCalled();
        $request->query = $query;

        $request->request = new InputBag();

        $this->onKernelRequest($requestEvent);
    }

    public function it_sets_defined_post_parameters_as_header(RequestEvent $requestEvent, Request $request, HeaderBag $headerBag): void
    {
        $headerList = [
            'Authorization'            => 'Authorization',
            'X-Experience-API-Version' => 'X-Experience-API-Version',
            'Content-Type'             => 'Content-Type',
            'Content-Length'           => 'Content-Length',
            'If-Match'                 => 'If-Match',
            'If-None-Match'            => 'If-None-Match'
        ];

        foreach ($headerList as $key => $value) {
            $headerBag->set($key, $value)->shouldBeCalled();
        }

        $request->request = new InputBag($headerList);
        $request->query = new InputBag(['method' => 'GET']);
        $request->isMethod(Request::METHOD_POST)->willReturn(true);
        $request->setMethod('GET')->shouldBeCalled();

        $requestEvent->getRequest()->willReturn($request);

        $this->onKernelRequest($requestEvent);
    }

    public function it_sets_content_from_post_parameters(RequestEvent $requestEvent, Request $request, ParameterBag $attributes, FileBag $fileBag, ServerBag $serverBag): void
    {
        $attributes->all()->shouldBeCalled()->willReturn([]);
        $fileBag->all()->shouldBeCalled()->willReturn([]);
        $serverBag->all()->shouldBeCalled()->willReturn([]);

        $request->attributes = $attributes;
        $request->cookies = new InputBag();
        $request->files = $fileBag;
        $request->query = new InputBag(['method' => 'POST']);
        $request->request = new InputBag(['content' => 'a content']);
        $request->server = $serverBag;

        $request->isMethod(Request::METHOD_POST)->willReturn(true);
        $request->setMethod('POST')->shouldBeCalled();

        $request->initialize(
            [],
            [],
            [],
            [],
            [],
            [],
            'a content'
        )->shouldBeCalled();

        $requestEvent->getRequest()->willReturn($request);

        $this->onKernelRequest($requestEvent);
    }
}
