<?php

namespace spec\XApi\LrsBundle\EventListener;

use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use PhpSpec\ObjectBehavior;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Xabbuh\XApi\Serializer\StatementSerializerInterface;
use XApi\Fixtures\Json\StatementJsonFixtures;

class SerializerListenerSpec extends ObjectBehavior
{
    public function let(StatementSerializerInterface $statementSerializer, GetResponseEvent $getResponseEvent, Request $request, ParameterBag $parameterBag): void
    {
        $parameterBag->has('xapi_lrs.route')->willReturn(true);

        $request->attributes = $parameterBag;

        $getResponseEvent->getRequest()->willReturn($request);

        $this->beConstructedWith($statementSerializer);
    }

    public function it_returns_null_if_request_has_no_attribute_xapi_lrs_route(GetResponseEvent $getResponseEvent, ParameterBag $parameterBag): void
    {
        $parameterBag->has('xapi_lrs.route')->shouldBeCalled()->willReturn(false);
        $parameterBag->get('xapi_serializer')->shouldNotBeCalled();

        $this->onKernelRequest($getResponseEvent)->shouldReturn(null);
    }

    public function it_sets_unserialized_data_as_request_attributes(StatementSerializerInterface $statementSerializer, GetResponseEvent $getResponseEvent, Request $request, ParameterBag $parameterBag): void
    {
        $jsonString = StatementJsonFixtures::getTypicalStatement();

        $statementSerializer->deserializeStatement($jsonString)->shouldBeCalled();

        $parameterBag->get('xapi_serializer')->willReturn('statement');
        $parameterBag->set('statement', null)->shouldBeCalled();

        $request->getContent()->shouldBeCalled()->willReturn($jsonString);

        $this->onKernelRequest($getResponseEvent);
    }

    public function it_throws_a_badrequesthttpexception_if_the_serializer_fails(StatementSerializerInterface $statementSerializer, GetResponseEvent $getResponseEvent, Request $request, ParameterBag $parameterBag): void
    {
        $statementSerializer->deserializeStatement(null)->shouldBeCalled()->willThrow(InvalidArgumentException::class);

        $parameterBag->get('xapi_serializer')->willReturn('statement');

        $request->attributes = $parameterBag;

        $this
            ->shouldThrow(BadRequestHttpException::class)
            ->during('onKernelRequest', [$getResponseEvent]);
    }
}
