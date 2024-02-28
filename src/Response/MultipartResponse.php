<?php

/*
 * This file is part of the xAPI package.
 *
 * (c) Christian Flothmann <christian.flothmann@xabbuh.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace XApi\LrsBundle\Response;

use LogicException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @author Jérôme Parmentier <jerome.parmentier@acensi.fr>
 */
class MultipartResponse extends Response
{
    protected ?string $subtype = null;

    protected string $boundary;

    /**
     * @var Response[]
     */
    protected $parts;

    /**
     * @param JsonResponse $jsonResponse
     * @param AttachmentResponse[] $attachmentsParts
     * @param int $status
     * @param array $headers
     * @param null|string $subtype
     */
    public function __construct(
        protected JsonResponse $jsonResponse,
        array $attachmentsParts = [],
        int $status = 200,
        array $headers = [],
        ?string $subtype = null
    ) {
        parent::__construct(null, $status, $headers);

        if (null === $subtype) {
            $subtype = 'mixed';
        }

        $this->subtype = $subtype;
        $this->boundary = uniqid('', true);

        $this->setAttachmentsParts($attachmentsParts);
    }

    public function addAttachmentPart(AttachmentResponse $attachmentResponse): static
    {
        if ($attachmentResponse->getContent() !== null) {
            $this->parts[] = $attachmentResponse;
        }

        return $this;
    }

    /**
     * @param AttachmentResponse[] $attachmentsParts
     */
    public function setAttachmentsParts(array $attachmentsParts): static
    {
        $this->parts = [$this->jsonResponse];

        foreach ($attachmentsParts as $attachmentPart) {
            $this->addAttachmentPart($attachmentPart);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function prepare(Request $request): MultipartResponse|Response
    {
        foreach ($this->parts as $part) {
            $part->prepare($request);
        }

        $this->headers->set('Content-Type', sprintf('multipart/%s; boundary="%s"', $this->subtype, $this->boundary));
        $this->headers->set('Transfer-Encoding', 'chunked');

        return parent::prepare($request);
    }

    /**
     * {@inheritdoc}
     */
    public function sendContent(): MultipartResponse|Response|static
    {
        $content = '';
        foreach ($this->parts as $part) {
            $content .= sprintf('--%s', $this->boundary) . "\r\n";
            $content .= $part->headers . "\r\n";
            $content .= $part->getContent();
            $content .= "\r\n";
        }

        $content .= sprintf('--%s--', $this->boundary) . "\r\n";

        echo $content;

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * @throws LogicException when the content is not null
     */
    public function setContent($content): void
    {
        if (null !== $content) {
            throw new LogicException('The content cannot be set on a MultipartResponse instance.');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getContent(): bool
    {
        return false;
    }
}
