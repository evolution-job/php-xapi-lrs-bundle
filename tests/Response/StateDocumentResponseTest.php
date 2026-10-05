<?php

/*
 * This file is part of the xAPI package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace XApi\LrsBundle\Tests\Response;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;
use XApi\LrsBundle\Response\StateDocumentResponse;

/**
 * @author Mathieu Boldo <mathieu.boldo@entrili.com>
 */
class StateDocumentResponseTest extends TestCase
{
    public function testRawDocumentBodyAndOriginalContentTypeArePreservedWithSha1Etag(): void
    {
        $body = "\x00raw document\nwith bytes";
        $response = new StateDocumentResponse($body, contentType: 'text/plain; charset=utf-8');

        self::assertSame($body, $response->getContent());
        self::assertSame('text/plain; charset=utf-8', $response->headers->get('Content-Type'));
        self::assertSame('"'.sha1($body).'"', $response->headers->get('ETag'));
    }

    public function testJsonDocumentIsEncodedAndUsesSha1OfResponseBody(): void
    {
        $response = new StateDocumentResponse(['name' => 'state']);
        $body = '{"name":"state"}';

        self::assertSame($body, $response->getContent());
        self::assertSame('application/json', $response->headers->get('Content-Type'));
        self::assertSame('"'.sha1($body).'"', $response->headers->get('ETag'));
    }

    public function testHeadOmitsBodyButRetainsTheGetEtag(): void
    {
        $body = 'state document';

        $getResponse = new StateDocumentResponse($body);
        $headResponse = new StateDocumentResponse($body, Response::HTTP_OK, isHeadRequest: true);

        self::assertSame('', $headResponse->getContent());
        self::assertSame($getResponse->headers->get('ETag'), $headResponse->headers->get('ETag'));
    }
}
