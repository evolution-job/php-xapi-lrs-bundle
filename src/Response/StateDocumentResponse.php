<?php

namespace XApi\LrsBundle\Response;

use DateTimeImmutable;
use JsonException;
use Symfony\Component\HttpFoundation\Response;
use XApi\LrsBundle\App\XapiHeader;
use XApi\LrsBundle\App\XapiVersion;

/**
 * @author Mathieu Boldo <mathieu.boldo@entrili.com>
 */
final class StateDocumentResponse extends Response
{
    /**
     * @throws JsonException
     */
    public function __construct(
        mixed $data,
        int $status = Response::HTTP_OK,
        array $headers = [],
        bool $isHeadRequest = false,
        ?string $contentType = null
    )
    {
        if (is_string($data)) {
            $content = $data;
            $contentType ??= 'application/octet-stream';
        } else {
            $content = null === $data ? '' : json_encode($data, JSON_THROW_ON_ERROR);
            $contentType ??= 'application/json';
        }

        $headers[XapiHeader::VERSION] = XapiVersion::V1_0_3;
        $headers[XapiHeader::CONSISTENT_THROUGH_HEADER] = new DateTimeImmutable()->format(XapiHeader::DATE_FORMAT);
        $headers['Content-Type'] = $contentType;

        parent::__construct($isHeadRequest ? null : $content, $status, $headers);

        $this->setEtag(sha1($content));
    }
}
