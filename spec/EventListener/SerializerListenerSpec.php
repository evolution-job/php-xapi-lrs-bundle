<?php

namespace spec\XApi\LrsBundle\EventListener;

use PhpSpec\ObjectBehavior;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Xabbuh\XApi\Common\Exception\BadRequestException;
use Xabbuh\XApi\DataFixtures\StatementFixtures;
use Xabbuh\XApi\Serializer\StatementSerializerInterface;
use Xabbuh\XApi\Serializer\StateSerializerInterface;
use XApi\Fixtures\Json\StatementJsonFixtures;
use XApi\LrsBundle\EventListener\XapiRequestDeserializer;
use XApi\LrsBundle\EventListener\XapiRequestMatcher;

/**
 * @author Christian Flothmann <christian.flothmann@xabbuh.de>
 * @author Mathieu Boldo <mathieu.boldo@entrili.com>
 */
class SerializerListenerSpec extends ObjectBehavior
{
    public function let(StatementSerializerInterface $statementSerializer, StateSerializerInterface $stateSerializer, RequestEvent $requestEvent, Request $request, ParameterBag $parameterBag): void
    {
        $parameterBag->has('xapi_lrs.route')->willReturn(true);
        $parameterBag->get('_route', '')->willReturn('xapi_lrs.route');
        $request->attributes = $parameterBag;

        $requestEvent->getRequest()->willReturn($request);
        $requestEvent->isMainRequest()->willReturn(true);

        $xapiRequestMatcher = new XapiRequestMatcher();
        $xapiRequestDeserializer = new XapiRequestDeserializer($statementSerializer->getWrappedObject(), $stateSerializer->getWrappedObject());
        $this->beConstructedWith($xapiRequestDeserializer, $xapiRequestMatcher);
    }

    public function it_returns_null_if_request_has_no_attribute_xapi_lrs_route(RequestEvent $requestEvent, ParameterBag $parameterBag): void
    {
        $parameterBag->has('xapi_lrs.route')->shouldBeCalled()->willReturn(false);
        $parameterBag->get('xapi_serializer')->shouldNotBeCalled();

        $this->onKernelRequest($requestEvent)->shouldReturn(null);
    }

    public function it_sets_unserialized_data_as_request_attributes(RequestEvent $requestEvent, Request $request, StatementSerializerInterface $statementSerializer, ParameterBag $parameterBag): void
    {
        $jsonString = StatementJsonFixtures::getTypicalStatement();

        $statement = StatementFixtures::getTypicalStatement();

        $statementSerializer->deserializeStatement($jsonString)->shouldBeCalled()->willReturn($statement);

        $parameterBag->get('xapi_serializer')->willReturn('statement');
        $parameterBag->set('statement', $statement)->shouldBeCalled();

        $request->isMethod(Request::METHOD_OPTIONS)->willReturn(false);
        $request->getContent()->shouldBeCalled()->willReturn($jsonString);

        $this->onKernelRequest($requestEvent);
    }

    public function it_throws_a_BadRequestException_if_the_serializer_fails(RequestEvent $requestEvent, StatementSerializerInterface $statementSerializer, Request $request, ParameterBag $parameterBag): void
    {
        $statementSerializer->deserializeStatement('')->shouldBeCalled()->willThrow(InvalidArgumentException::class);

        $parameterBag->get('xapi_serializer')->willReturn('statement');
        $request->isMethod(Request::METHOD_OPTIONS)->willReturn(false);
        $request->getContent()->shouldBeCalled()->willReturn('');

        $request->attributes = $parameterBag;

        $this
            ->shouldThrow(BadRequestException::class)
            ->during('onKernelRequest', [$requestEvent]);
    }
}
