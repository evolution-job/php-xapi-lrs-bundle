<?php

/*
 * This file is part of the xAPI package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace XApi\LrsBundle\Service;

use JsonException;
use Xabbuh\XApi\Common\Exception\BadRequestException;

/**
 * Parses and validates multipart statement uploads.
 *
 * @author Mathieu Boldo <mathieu.boldo@entrili.com>
 */
final readonly class MultipartStatementParser
{
    /**
     * @return array{0: string, 1: array<string, array{content: string}>}
     * @throws BadRequestException
     */
    public function parse(string $content, ?string $contentType): array
    {
        $parts = $this->splitParts($content, $this->extractBoundary($contentType));
        $jsonContent = null;
        $attachments = [];
        $attachmentMetadata = [];

        foreach ($parts as $part) {
            [$headers, $body] = $this->parsePart($part);
            if (null === $headers) {
                continue;
            }

            if (null === $jsonContent) {
                $jsonContent = $this->getJsonStatementPart($headers, $body);
                $attachmentMetadata = $this->extractAttachmentMetadata($jsonContent);

                continue;
            }

            $sha2 = $this->validateAttachmentPart($headers, $body, $attachmentMetadata);
            $attachments[$sha2] = ['content' => $body];
        }

        if (null === $jsonContent) {
            throw new BadRequestException('The multipart request does not contain a JSON statement payload.');
        }

        return [$jsonContent, $attachments];
    }

    private function extractBoundary(?string $contentType): string
    {
        if (null === $contentType || !preg_match('/boundary=(?:"([^"]+)"|([^;]+))/', $contentType, $matches)) {
            throw new BadRequestException('Multipart requests must declare a valid boundary.');
        }

        $boundary = trim('' !== $matches[1] ? $matches[1] : ($matches[2] ?? ''), "\" ");
        if ('' === $boundary) {
            throw new BadRequestException('Multipart requests must declare a valid boundary.');
        }

        return $boundary;
    }

    /**
     * @return string[]
     */
    private function splitParts(string $content, string $boundary): array
    {
        $parts = preg_split(
            '/(?:^|\r\n|\n|\r)--'.preg_quote($boundary, '/').'(--)?(?:\r\n|\n|\r|$)/',
            $content,
            -1,
            PREG_SPLIT_NO_EMPTY
        );

        if (!is_array($parts) || [] === $parts) {
            throw new BadRequestException('The multipart payload could not be parsed.');
        }

        return $parts;
    }

    /**
     * @return array{0: array<string, string>|null, 1: string}
     */
    private function parsePart(string $part): array
    {
        if (!preg_match('/\r\n\r\n|\n\n|\r\r/', $part, $separator, PREG_OFFSET_CAPTURE)) {
            return [null, ''];
        }

        $separatorPosition = $separator[0][1];
        $separatorLength = strlen($separator[0][0]);
        $headers = substr($part, 0, $separatorPosition);
        $body = substr($part, $separatorPosition + $separatorLength);
        $headerLines = preg_split('/\r\n|\n|\r/', $headers, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($headerLines)) {
            return [null, ''];
        }

        $headerMap = [];
        foreach ($headerLines as $headerLine) {
            [$name, $value] = array_pad(explode(':', $headerLine, 2), 2, '');
            $headerMap[strtolower(trim($name))] = trim($value);
        }

        return [$headerMap, $body];
    }

    /**
     * @param array<string, string> $headers
     */
    private function getJsonStatementPart(array $headers, string $body): string
    {
        $mediaType = strtolower(trim(explode(';', $headers['content-type'] ?? '', 2)[0]));
        if ('application/json' !== $mediaType) {
            throw new BadRequestException('The first multipart part must have a Content-Type of application/json.');
        }

        return ltrim($body);
    }

    /**
     * @param array<string, string> $headers
     * @param array<string, array<array{contentType: string, length: int}>> $attachmentMetadata
     */
    private function validateAttachmentPart(array $headers, string $body, array $attachmentMetadata): string
    {
        if ('binary' !== strtolower(trim($headers['content-transfer-encoding'] ?? ''))) {
            throw new BadRequestException('Multipart attachment parts must declare Content-Transfer-Encoding: binary.');
        }

        $sha2 = strtolower(trim($headers['x-experience-api-hash'] ?? ''));
        $algorithm = match (strlen($sha2)) {
            64 => 'sha256',
            96 => 'sha384',
            128 => 'sha512',
            default => null,
        };

        if (
            null === $algorithm
            || 1 !== preg_match('/\A[a-f0-9]+\z/', $sha2)
            || !hash_equals($sha2, hash($algorithm, $body))
        ) {
            throw new BadRequestException('A multipart attachment has an invalid or mismatched X-Experience-API-Hash header.');
        }

        if (!isset($attachmentMetadata[$sha2])) {
            throw new BadRequestException('A multipart attachment does not match any attachment in the statement payload.');
        }

        $this->validateAttachmentMetadata($headers, $body, $attachmentMetadata[$sha2]);

        return $sha2;
    }

    /**
     * @param array<string, string> $headers
     * @param array<array{contentType: string, length: int}> $metadata
     */
    private function validateAttachmentMetadata(array $headers, string $body, array $metadata): void
    {
        $actualLength = strlen($body);

        foreach ($metadata as $attachment) {
            if ($attachment['length'] !== $actualLength) {
                throw new BadRequestException('A multipart attachment length does not match the statement attachment metadata.');
            }

            if (
                isset($headers['content-type'])
                && strtolower(trim($headers['content-type'])) !== strtolower(trim($attachment['contentType']))
            ) {
                throw new BadRequestException('A multipart attachment Content-Type does not match the statement attachment metadata.');
            }

            if (
                isset($headers['content-length'])
                && !$this->contentLengthMatches($headers['content-length'], $actualLength)
            ) {
                throw new BadRequestException('A multipart attachment Content-Length does not match the attachment data length.');
            }
        }
    }

    /**
     * @return array<string, array<array{contentType: string, length: int}>>
     */
    private function extractAttachmentMetadata(string $jsonContent): array
    {
        try {
            $data = json_decode($jsonContent, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }

        if (!is_array($data)) {
            return [];
        }

        $statements = array_is_list($data) ? $data : [$data];
        $metadata = [];
        foreach ($statements as $statement) {
            if (is_array($statement)) {
                $this->collectAttachmentMetadata($statement, $metadata);
            }
        }

        return $metadata;
    }

    /**
     * @param array<string, mixed> $statement
     * @param array<string, array<array{contentType: string, length: int}>> $metadata
     */
    private function collectAttachmentMetadata(array $statement, array &$metadata): void
    {
        foreach ($statement['attachments'] ?? [] as $attachment) {
            if (
                is_array($attachment)
                && is_string($attachment['sha2'] ?? null)
                && is_string($attachment['contentType'] ?? null)
                && is_int($attachment['length'] ?? null)
            ) {
                $metadata[strtolower($attachment['sha2'])][] = [
                    'contentType' => $attachment['contentType'],
                    'length' => $attachment['length'],
                ];
            }
        }

        $object = $statement['object'] ?? null;
        if (is_array($object) && 'SubStatement' === ($object['objectType'] ?? null)) {
            $this->collectAttachmentMetadata($object, $metadata);
        }
    }

    private function contentLengthMatches(string $header, int $actualLength): bool
    {
        if (1 !== preg_match('/\A\d+\z/', trim($header))) {
            return false;
        }

        $declaredLength = ltrim(trim($header), '0');

        return ('' === $declaredLength ? '0' : $declaredLength) === (string) $actualLength;
    }
}
