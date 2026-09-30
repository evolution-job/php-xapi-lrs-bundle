<?php

namespace spec\XApi\LrsBundle\EventListener;

use PhpSpec\ObjectBehavior;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * @author Mathieu Boldo <mathieu.boldo@entrili.com>
 */
class TunnelingListenerSpec extends ObjectBehavior
{
    public function let(Request $request, ParameterBag $parameterBag): void
    {
        $parameterBag->has('xapi_lrs.route')->willReturn(true);
        $parameterBag->get('_route', '')->willReturn('xapi_lrs.route');
        $request->attributes = $parameterBag;
        $request->isMethod('POST')->willReturn(true);
    }

    public function it_is_initializable()
    {
        $this->shouldHaveType('XApi\LrsBundle\EventListener\TunnelingListener');
    }

    public function it_tunnels_a_post_request_to_put(HttpKernelInterface $kernel, Request $request)
    {
        $request->query = new InputBag(['method' => 'PUT']);
        $request->setMethod('PUT')->shouldBeCalled();
        $event = new RequestEvent(
            $kernel->getWrappedObject(),
            $request->getWrappedObject(),
            HttpKernelInterface::MAIN_REQUEST
        );

        $this->onKernelRequest($event);
    }

    public function it_does_nothing_if_method_parameter_is_missing(HttpKernelInterface $kernel, Request $request)
    {
        $request->isMethod('POST')->willReturn(true);
        $request->query = new InputBag([]); // No method parameter

        $request->setMethod()->shouldNotBeCalled();

        $event = new RequestEvent(
            $kernel->getWrappedObject(),
            $request->getWrappedObject(),
            HttpKernelInterface::MAIN_REQUEST
        );

        $this->onKernelRequest($event);
    }

    public function it_does_nothing_if_the_initial_method_is_not_post(HttpKernelInterface $kernel, Request $request)
    {
        $request->isMethod('POST')->willReturn(false); // GET example

        $event = new RequestEvent(
            $kernel->getWrappedObject(),
            $request->getWrappedObject(),
            HttpKernelInterface::MAIN_REQUEST
        );

        $this->onKernelRequest($event);
    }
}
