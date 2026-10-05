<?php

/*
 * This file is part of the xAPI package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace XApi\LrsBundle\Tests\Response;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use XApi\LrsBundle\Response\MultipartResponse;

/**
 * @author Mathieu Boldo <mathieu.boldo@entrili.com>
 */
class MultipartResponseTest extends TestCase
{
    public function testHeadRequestDoesNotSendMultipartBody(): void
    {
        $response = new MultipartResponse(new JsonResponse(['statements' => []]));
        $response->prepare(new Request(server: ['REQUEST_METHOD' => Request::METHOD_HEAD]));

        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        self::assertSame('', $content);
        self::assertStringStartsWith('multipart/mixed;', $response->headers->get('Content-Type'));
    }

    public function testGetRequestSendsMultipartBody(): void
    {
        $response = new MultipartResponse(new JsonResponse(['statements' => []]));
        $response->prepare(new Request(server: ['REQUEST_METHOD' => Request::METHOD_GET]));

        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        self::assertNotSame('', $content);
        self::assertStringContainsString('"statements":[]', $content);
    }
}
