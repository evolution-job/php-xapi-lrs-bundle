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
use Symfony\Component\Routing\Router;
use XApi\Fixtures\Json\StatementJsonFixtures;
use XApi\LrsBundle\EventListener\XapiRequestMatcher;
use XApi\LrsBundle\Exception\BadRequestHttpException;

/**
 * @author Jérôme Parmentier <jerome.parmentier@acensi.fr>
 * @author Mathieu Boldo <mathieu.boldo@entrili.com>
 */
class AlternateRequestSyntaxListenerSpec extends ObjectBehavior
{
    public function let(Router $router, RequestEvent $requestEvent, Request $request, HeaderBag $headerBag): void
    {
        $request->attributes = new ParameterBag([
            'xapi_lrs.route' => true,
            '_route'         => 'xapi_lrs.my.route',
        ]);
        $request->headers = $headerBag;
        $request->query = new InputBag(['method' => 'POST']);
        $request->request = new InputBag();
        $request->getMethod()->willReturn('POST');

        $requestEvent->isMainRequest()->willReturn(true);
        $requestEvent->getRequest()->willReturn($request);

        $router->matchRequest($request)->willReturn(['xapi_lrs.route' => true]);

        $xapiRequestMatcher = new XapiRequestMatcher();
        $this->beConstructedWith($router, $xapiRequestMatcher);
    }

    public function it_returns_null_if_request_is_not_main(RequestEvent $requestEvent): void
    {
        $requestEvent->isMainRequest()->willReturn(false);
        $requestEvent->getRequest()->shouldNotBeCalled();

        $this->onKernelRequest($requestEvent)->shouldReturn(null);
    }

    public function it_returns_null_if_request_has_no_attribute_xapi_lrs_route(RequestEvent $requestEvent, Request $request, ParameterBag $parameterBag): void
    {
        $parameterBag->has('xapi_lrs.route')->shouldNotBeCalled();

        $request->isMethod('POST')->willReturn(false);

        $request->attributes = $parameterBag;
        $requestEvent->getRequest()->willReturn($request);

        $this->onKernelRequest($requestEvent)->shouldReturn(null);
    }

    public function it_returns_null_if_request_method_is_get(RequestEvent $requestEvent, Request $request, ParameterBag $parameterBag): void
    {
        $parameterBag->get('method')->shouldNotBeCalled();

        $request->isMethod('POST')->willReturn(false);

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

    public function it_sets_the_request_method_equals_to_method_query_parameter(RequestEvent $requestEvent, Request $request): void
    {
        $request->isMethod(Request::METHOD_POST)->willReturn(true);
        $request->getContent()->willReturn(null);

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
            'If-Match'                 => 'If-Match',
            'If-None-Match'            => 'If-None-Match',
        ];

        foreach ($headerList as $key => $value) {
            $headerBag->set($key, $value)->shouldBeCalled();
        }

        $request->request = new InputBag($headerList);
        $request->query = new InputBag(['method' => 'GET']);
        $request->isMethod(Request::METHOD_POST)->willReturn(true);
        $request->setMethod('GET')->shouldBeCalled();
        $request->getContent()->willReturn(null);

        $requestEvent->getRequest()->willReturn($request);

        $this->onKernelRequest($requestEvent);
    }

    public function it_sets_content_from_post_parameters(RequestEvent $requestEvent, Request $request, FileBag $fileBag, ServerBag $serverBag): void
    {
        $fileBag->count()->shouldBeCalled()->willReturn(0);
        $fileBag->all()->shouldBeCalled()->willReturn([]);
        $serverBag->all()->shouldBeCalled()->willReturn([]);

        $request->cookies = new InputBag();
        $request->files = $fileBag;
        $request->query = new InputBag(['method' => 'POST']);
        $request->request = new InputBag(['content' => StatementJsonFixtures::getMinimalStatement()]);
        $request->server = $serverBag;

        $request->isMethod(Request::METHOD_POST)->willReturn(true);
        $request->setMethod('POST')->shouldBeCalled();

        $request->initialize(
            [],
            [],
            ['xapi_lrs.route' => true, '_route' => 'xapi_lrs.my.route'],
            [],
            [],
            [],
            StatementJsonFixtures::getMinimalStatement()
        )->shouldBeCalled();

        $requestEvent->getRequest()->willReturn($request);

        $this->onKernelRequest($requestEvent);
    }
}
