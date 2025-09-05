<?php

namespace spec\XApi\LrsBundle\Response;

use PhpSpec\ObjectBehavior;
use Symfony\Component\HttpFoundation\Request;
use Xabbuh\XApi\DataFixtures\AttachmentFixtures;
use Xabbuh\XApi\Model\Attachment;

class AttachmentResponseSpec extends ObjectBehavior
{
    private ?Attachment $attachment = null;

    public function let(): void
    {
        $this->attachment = AttachmentFixtures::getTextAttachment();

        $this->beConstructedWith($this->attachment);
    }

    public function it_should_throw_a_logicexception_when_sending_content(): void
    {
        $this
            ->shouldThrow('\LogicException')
            ->during('sendContent');
    }

    public function it_should_throw_a_logicexception_when_setting_content(): void
    {
        $this
            ->shouldThrow('\LogicException')
            ->during('setContent', ['a custom content']);
    }

    public function it_should_return_content_of_the_attachment(): void
    {
        $this->getContent()->shouldBe($this->attachment->getContent());
    }

    public function it_should_set_Content_Type_header_equals_to_ContentType_property_of_attachment(): void
    {
        $request = new Request();

        $this->prepare($request);

        $this->headers->get('Content-Type')->shouldBe($this->attachment->getContentType());
    }

    public function it_should_set_Content_Transfer_Encoding_header_equals_to_binary(): void
    {
        $request = new Request();

        $this->prepare($request);

        $this->headers->get('Content-Transfer-Encoding')->shouldBe('binary');
    }

    public function it_should_set_X_Experience_API_Hash_header_equals_to_sha2_property_of_attachment(): void
    {
        $request = new Request();

        $this->prepare($request);

        $this->headers->get('X-Experience-API-Hash')->shouldBe($this->attachment->getSha2());
    }
}
