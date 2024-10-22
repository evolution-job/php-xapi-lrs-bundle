<?php

namespace spec\XApi\LrsBundle\EventListener;

use ArrayIterator;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use PhpSpec\ObjectBehavior;
use Symfony\Component\HttpFoundation\FileBag;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ServerBag;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;

class AlternateRequestSyntaxListenerSpec extends ObjectBehavior
{
    public function let(GetResponseEvent $getResponseEvent, Request $request, ParameterBag $query, ParameterBag $post, ParameterBag $attributes, HeaderBag $headerBag): void
    {
        $query->count()->willReturn(1);
        $query->get('method')->willReturn('GET');

        $post->getIterator()->willReturn(new ArrayIterator());
        $post->get('content')->willReturn(null);

        $attributes->has('xapi_lrs.route')->willReturn(true);

        $request->query = $query;
        $request->request = $post;
        $request->attributes = $attributes;
        $request->headers = $headerBag;
        $request->getMethod()->willReturn('POST');

        $getResponseEvent->isMasterRequest()->willReturn(true);
        $getResponseEvent->getRequest()->willReturn($request);
    }

    public function it_returns_null_if_request_is_not_master(GetResponseEvent $getResponseEvent): void
    {
        $getResponseEvent->isMasterRequest()->willReturn(false);
        $getResponseEvent->getRequest()->shouldNotBeCalled();

        $this->onKernelRequest($getResponseEvent)->shouldReturn(null);
    }

    public function it_returns_null_if_request_has_no_attribute_xapi_lrs_route(GetResponseEvent $getResponseEvent, ParameterBag $parameterBag): void
    {
        $parameterBag->has('xapi_lrs.route')->shouldBeCalled()->willReturn(false);

        $this->onKernelRequest($getResponseEvent)->shouldReturn(null);
    }

    public function it_returns_null_if_request_method_is_get(GetResponseEvent $getResponseEvent, Request $request, ParameterBag $parameterBag): void
    {
        $parameterBag->get('method')->shouldNotBeCalled();
        $request->getMethod()->willReturn('GET');

        $this->onKernelRequest($getResponseEvent)->shouldReturn(null);
    }

    public function it_returns_null_if_request_method_is_put(GetResponseEvent $getResponseEvent, Request $request, ParameterBag $parameterBag): void
    {
        $parameterBag->get('method')->shouldNotBeCalled();
        $request->getMethod()->willReturn('PUT');

        $this->onKernelRequest($getResponseEvent)->shouldReturn(null);
    }

    public function it_throws_a_badrequesthttpexception_if_other_query_parameter_than_method_is_set(GetResponseEvent $getResponseEvent, ParameterBag $parameterBag): void
    {
        $parameterBag->count()->shouldBeCalled()->willReturn(2);

        $this
            ->shouldThrow(BadRequestHttpException::class)
            ->during('onKernelRequest', [$getResponseEvent]);
    }

    public function it_sets_the_request_method_equals_to_method_query_parameter(GetResponseEvent $getResponseEvent, Request $request, ParameterBag $parameterBag): void
    {
        $parameterBag->remove('method')->shouldBeCalled();
        $request->setMethod('GET')->shouldBeCalled();

        $this->onKernelRequest($getResponseEvent);
    }

    public function it_sets_defined_post_parameters_as_header(GetResponseEvent $getResponseEvent, Request $request, ParameterBag $query, ParameterBag $post, HeaderBag $headerBag): void
    {
        $request->setMethod('GET')->shouldBeCalled();
        $query->remove('method')->shouldBeCalled();

        $headerList = ['Authorization' => 'Authorization', 'X-Experience-API-Version' => 'X-Experience-API-Version', 'Content-Type' => 'Content-Type', 'Content-Length' => 'Content-Length', 'If-Match' => 'If-Match', 'If-None-Match' => 'If-None-Match'];

        $post->getIterator()->shouldBeCalled()->willReturn(new ArrayIterator($headerList));

        foreach ($headerList as $key => $value) {
            $post->remove($key)->shouldBeCalled();

            $headerBag->set($key, $value)->shouldBeCalled();
        }

        $this->onKernelRequest($getResponseEvent);
    }

    public function it_sets_other_post_parameters_as_query_parameters(GetResponseEvent $getResponseEvent, Request $request, ParameterBag $query, ParameterBag $post): void
    {
        $request->setMethod('GET')->shouldBeCalled();
        $query->remove('method')->shouldBeCalled();

        $parameterList = ['token' => 'a-token', 'attachments' => true];

        $post->getIterator()->shouldBeCalled()->willReturn(new ArrayIterator($parameterList));

        foreach ($parameterList as $key => $value) {
            $post->remove($key)->shouldBeCalled();

            $query->set($key, $value)->shouldBeCalled();
        }

        $this->onKernelRequest($getResponseEvent);
    }

    public function it_sets_content_from_post_parameters(GetResponseEvent $getResponseEvent, Request $request, ParameterBag $query, ParameterBag $post, ParameterBag $attributes, ParameterBag $cookies, FileBag $fileBag, ServerBag $serverBag): void
    {
        $query->all()->shouldBeCalled()->willReturn([]);
        $query->remove('method')->shouldBeCalled();

        $post->all()->shouldBeCalled()->willReturn([]);
        $post->get('content')->shouldBeCalled()->willReturn('a content');
        $post->remove('content')->shouldBeCalled();

        $attributes->all()->shouldBeCalled()->willReturn([]);
        $cookies->all()->shouldBeCalled()->willReturn([]);
        $fileBag->all()->shouldBeCalled()->willReturn([]);
        $serverBag->all()->shouldBeCalled()->willReturn([]);

        $request->setMethod('GET')->shouldBeCalled();
        $request->initialize(
            [],
            [],
            [],
            [],
            [],
            [],
            'a content'
        )->shouldBeCalled();

        $request->cookies = $cookies;
        $request->files = $fileBag;
        $request->server = $serverBag;

        $this->onKernelRequest($getResponseEvent);
    }
}
