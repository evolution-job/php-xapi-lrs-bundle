<?php

/*
 * This file is part of the xAPI package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace XApi\LrsBundle\Tests\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Xabbuh\XApi\Common\Exception\BadRequestException;
use XApi\LrsBundle\Service\MultipartStatementParser;

/**
 * @author Mathieu Boldo <mathieu.boldo@entrili.com>
 */
class MultipartStatementParserTest extends TestCase
{
    private MultipartStatementParser $parser;

    protected function setUp(): void
    {
        $this->parser = new MultipartStatementParser();
    }

    public function testParsesQuotedBoundaryAndPreservesAttachmentBytes(): void
    {
        $content = "\x00\xFF binary \r\n bytes";
        $hash = hash('sha256', $content);
        $json = $this->statementJson($hash, $content, 'application/octet-stream');

        [$parsedJson, $attachments] = $this->parser->parse(
            $this->multipartBody($json, $content, $hash, 'application/octet-stream'),
            'multipart/mixed; boundary="quoted-boundary"'
        );

        self::assertSame($json, $parsedJson);
        self::assertSame([$hash => ['content' => $content]], $attachments);
    }

    #[DataProvider('invalidContentTypes')]
    public function testRejectsMissingOrEmptyBoundary(?string $contentType): void
    {
        $this->expectException(BadRequestException::class);

        $this->parser->parse('', $contentType);
    }

    public static function invalidContentTypes(): array
    {
        return [
            'missing content type' => [null],
            'missing boundary' => ['multipart/mixed'],
            'empty boundary' => ['multipart/mixed; boundary=""'],
        ];
    }

    public function testRejectsFirstPartWithoutApplicationJsonContentType(): void
    {
        $json = '{"actor":{},"verb":{},"object":{}}';
        $body = implode("\r\n", [
            '--boundary',
            '',
            $json,
            '--boundary--',
            '',
        ]);

        $this->expectException(BadRequestException::class);

        $this->parser->parse($body, 'multipart/mixed; boundary=boundary');
    }

    public function testRejectsAttachmentWithoutBinaryTransferEncoding(): void
    {
        $content = 'attachment data';
        $hash = hash('sha256', $content);
        $json = $this->statementJson($hash, $content, 'text/plain');
        $body = implode("\r\n", [
            '--boundary',
            'Content-Type: application/json',
            '',
            $json,
            '--boundary',
            'Content-Type: text/plain',
            'X-Experience-API-Hash: '.$hash,
            '',
            $content,
            '--boundary--',
            '',
        ]);

        $this->expectException(BadRequestException::class);

        $this->parser->parse($body, 'multipart/mixed; boundary=boundary');
    }

    public function testRejectsAttachmentThatDoesNotMatchStatementMetadata(): void
    {
        $content = 'attachment data';
        $hash = hash('sha256', $content);
        $json = '{"actor":{},"verb":{},"object":{},"attachments":[]}';
        $body = $this->multipartBody($json, $content, $hash, 'text/plain');

        $this->expectException(BadRequestException::class);

        $this->parser->parse($body, 'multipart/mixed; boundary=quoted-boundary');
    }

    public function testRejectsAttachmentWhenMetadataLengthDoesNotMatchBytes(): void
    {
        $content = 'attachment data';
        $hash = hash('sha256', $content);
        $json = $this->statementJson($hash, $content, 'text/plain', strlen($content) + 1);
        $body = $this->multipartBody($json, $content, $hash, 'text/plain');

        $this->expectException(BadRequestException::class);

        $this->parser->parse($body, 'multipart/mixed; boundary=quoted-boundary');
    }

    public function testAllowsOptionalAttachmentContentTypeAndContentLengthHeaders(): void
    {
        $content = 'attachment data';
        $hash = hash('sha256', $content);
        $json = $this->statementJson($hash, $content, 'text/plain');
        $body = implode("\r\n", [
            '--boundary',
            'Content-Type: application/json',
            '',
            $json,
            '--boundary',
            'Content-Transfer-Encoding: binary',
            'X-Experience-API-Hash: '.$hash,
            '',
            $content,
            '--boundary--',
            '',
        ]);

        [$parsedJson, $attachments] = $this->parser->parse($body, 'multipart/mixed; boundary=boundary');

        self::assertSame($json, $parsedJson);
        self::assertSame([$hash => ['content' => $content]], $attachments);
    }

    private function statementJson(string $hash, string $content, string $contentType, ?int $length = null): string
    {
        return json_encode([
            'actor' => [],
            'verb' => [],
            'object' => [],
            'attachments' => [[
                'sha2' => $hash,
                'contentType' => $contentType,
                'length' => $length ?? strlen($content),
            ]],
        ], JSON_THROW_ON_ERROR);
    }

    private function multipartBody(string $json, string $attachmentContent, string $hash, string $contentType): string
    {
        return implode("\r\n", [
            '--quoted-boundary',
            'Content-Type: application/json; charset=utf-8',
            '',
            $json,
            '--quoted-boundary',
            'Content-Type: '.$contentType,
            'Content-Transfer-Encoding: binary',
            'Content-Length: '.strlen($attachmentContent),
            'X-Experience-API-Hash: '.$hash,
            '',
            $attachmentContent,
            '--quoted-boundary--',
            '',
        ]);
    }
}
