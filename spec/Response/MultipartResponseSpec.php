<?php

namespace spec\XApi\LrsBundle\Response;

use PhpSpec\ObjectBehavior;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class MultipartResponseSpec extends ObjectBehavior
{
    public function let(JsonResponse $jsonResponse): void
    {
        $this->beConstructedWith($jsonResponse);
    }

    public function it_should_throw_a_logicexception_when_setting_content(): void
    {
        $this
            ->shouldThrow('\LogicException')
            ->during('setContent', ['a custom content']);
    }

    public function it_should_set_Content_Type_header_of_a_multipart_response(): void
    {
        $request = new Request();

        $this->prepare($request);

        $this->headers->get('Content-Type')->shouldStartWith('multipart/mixed; boundary=');
    }
}
