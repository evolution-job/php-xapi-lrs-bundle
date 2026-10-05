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

use DateTimeImmutable;
use JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use XApi\LrsBundle\App\XapiHeader;
use XApi\LrsBundle\App\XapiVersion;


/**
 * @author Mathieu Boldo <mathieu.boldo@entrili.com>
 */
class JsonXapiResponse extends JsonResponse
{
    public function __construct(mixed $data = null, int $status = Response::HTTP_OK, array $headers = [], bool $json = false, bool $isHeadRequest = false)
    {
        $headers[XapiHeader::VERSION] = XapiVersion::V1_0_3;

        $now = new DateTimeImmutable()->format(XapiHeader::DATE_FORMAT);
        $headers[XapiHeader::CONSISTENT_THROUGH_HEADER] = $now;

        $etag = null;
        if ($data !== null) {
            if ($json && is_string($data)) {
                $etag = md5($data);
            } else {
                try {
                    $etag = md5(json_encode($data, JSON_THROW_ON_ERROR));
                } catch (JsonException) { }
            }
        }

        if ($isHeadRequest) {
            $data = null;
            $json = false;
        }

        Parent::__construct($data, $status, $headers, $json);

        if ($isHeadRequest) {
            $this->setContent(null);
        }

        if ($etag !== null) {
            $this->setEtag($etag);
        }
    }
}