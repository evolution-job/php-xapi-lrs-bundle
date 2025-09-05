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
use Override;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Xabbuh\XApi\Model\Attachment;

/**
 * @author Jérôme Parmentier <jerome.parmentier@acensi.fr>
 */
class AttachmentResponse extends Response
{
    public function __construct(protected Attachment $attachment)
    {
        parent::__construct(null);
    }

    /**
     * {@inheritdoc}
     */
    #[Override]
    public function prepare(Request $request): static
    {
        if (!$this->headers->has('Content-Type')) {
            $this->headers->set('Content-Type', $this->attachment->getContentType());
        }

        $this->headers->set('Content-Transfer-Encoding', 'binary');
        $this->headers->set('X-Experience-API-Hash', $this->attachment->getSha2());

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * @throws LogicException
     */
    #[Override]
    public function sendContent(): static
    {
        throw new LogicException('An AttachmentResponse is only meant to be part of a multipart Response.');
    }

    /**
     * {@inheritdoc}
     *
     * @throws LogicException when the content is not null
     */
    #[Override]
    public function setContent($content): static
    {
        if (null !== $content) {
            throw new LogicException('The content cannot be set on an AttachmentResponse instance.');
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    #[Override]
    public function getContent(): string|false
    {
        return $this->attachment->getContent();
    }
}
