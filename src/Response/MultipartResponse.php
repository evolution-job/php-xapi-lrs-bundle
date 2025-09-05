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

use Override;
use Symfony\Component\HttpFoundation\Exception\LogicException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @author Jérôme Parmentier <jerome.parmentier@acensi.fr>
 */
class MultipartResponse extends JsonResponse
{
    protected ?string $subtype = null;

    protected string $boundary;

    /**
     * @var Response[]
     */
    protected array $parts;

    /**
     * @param AttachmentResponse[] $attachmentsParts
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

    #[Override]
    public function prepare(Request $request): static
    {
        foreach ($this->parts as $part) {
            $part->prepare($request);
        }

        $this->headers->set('Content-Type', sprintf('multipart/%s; boundary="%s"', $this->subtype, $this->boundary));
        $this->headers->set('Transfer-Encoding', 'chunked');

        return parent::prepare($request);
    }

    #[Override]
    public function sendContent(): static
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
     * @throws LogicException when the content is not null
     */
    #[Override]
    public function setContent($content): static
    {
        if ($content === '{}') {
            return $this;
        }

        if (!empty($content)) {
            throw new LogicException('The content cannot be set on a MultipartResponse instance.');
        }

        return $this;
    }

    #[Override]
    public function getContent(): false|string
    {
        return false;
    }

    #[Override]
    protected function update(): static
    {
        if (null !== $this->callback) {
            // Not using application/javascript for compatibility reasons with older browsers.
            $this->headers->set('Content-Type', 'text/javascript');

            return $this->setContent(\sprintf('/**/%s(%s);', $this->callback, $this->data));
        }

        // Only set the header when there is none or when it equals 'text/javascript' (from a previous update with callback)
        // in order to not overwrite a custom definition.
        if (!$this->headers->has('Content-Type') || 'text/javascript' === $this->headers->get('Content-Type')) {
            $this->headers->set('Content-Type', 'application/json');
        }

        return $this;
    }
}
