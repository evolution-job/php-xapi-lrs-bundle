<?php

namespace spec\XApi\LrsBundle\Response;


use PhpSpec\ObjectBehavior;
use Symfony\Component\HttpFoundation\Exception\LogicException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ServerBag;

class MultipartResponseSpec extends ObjectBehavior
{
    public function let(JsonResponse $jsonResponse, Request $request, ServerBag $serverBag): void
    {
        $request->isMethod('HEAD')->willReturn(false);
        $request->isSecure()->willReturn(true);
        $request->server = $serverBag;

        $jsonResponse->prepare($request)->willReturn($jsonResponse);

        $this->beConstructedWith($jsonResponse);
    }

    public function it_should_throw_a_LogicException_when_setting_content(): void
    {
        $this
            ->shouldThrow(LogicException::class)
            ->during('setContent', ['a custom content']);
    }

    public function it_should_set_Content_Type_header_of_a_multipart_response(Request $request): void
    {
        $this->prepare($request);

        $this->headers->get('Content-Type')->shouldStartWith('multipart/mixed; boundary=');
    }
}
