<?php

/*
 * This file is part of the xAPI package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace XApi\LrsBundle\Service;

use JsonException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Xabbuh\XApi\Common\Exception\BadRequestException;
use Xabbuh\XApi\Common\Exception\UnsupportedStatementVersionException;
use Xabbuh\XApi\Model\State;
use Xabbuh\XApi\Model\Statement;
use Xabbuh\XApi\Serializer\Exception\DeserializationException;
use Xabbuh\XApi\Serializer\StatementSerializerInterface;
use Xabbuh\XApi\Serializer\StateSerializerInterface;

/**
 * @author Mathieu Boldo <mathieu.boldo@entrili.com>
 */
final readonly class RequestDeserializer
{
    public function __construct(
        private StatementSerializerInterface $statementSerializer,
        private StateSerializerInterface $stateSerializer
    ) { }

    /**
     * @throws BadRequestException
     */
    public function deserializeState(Request $request): State
    {
        $this->validateStateQueryParameters($request);

        try {
            $parameters = [];
            foreach ($request->query->all() as $key => $value) {
                if (is_string($value) && str_starts_with($value, '{') && str_ends_with($value, '}')) {
                    $value = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
                }

                $parameters[$key] = $value;
            }

            $state = $this->stateSerializer->deserializeState(
                json_encode($parameters, JSON_THROW_ON_ERROR),
                $request->getContent() ?? ''
            );

            if (in_array($request->getMethod(), [Request::METHOD_POST, Request::METHOD_PUT], true)) {
                $contentType = $this->getContentType($request);

                if ('application/json' !== $this->getMediaType($contentType)) {
                    $state = new State(
                        $state->getActivity(),
                        $state->getAgent(),
                        $state->getStateId(),
                        $state->getRegistrationId(),
                        $request->getContent(),
                        $contentType
                    );
                } else {
                    $state = $state->withContentType($contentType);
                }
            }

            if (
                null === $state->getStateId()
                && in_array($request->getMethod(), [Request::METHOD_POST, Request::METHOD_PUT], true)
            ) {
                throw new BadRequestException('The stateId parameter is required for this request.');
            }

            return $state;
        } catch (UnsupportedStatementVersionException|InvalidArgumentException|DeserializationException|JsonException) {
            throw $this->createBadRequestException('state');
        }
    }

    /**
     * @throws BadRequestException
     */
    private function validateStateQueryParameters(Request $request): void
    {
        $allowedParameters = match ($request->getMethod()) {
            Request::METHOD_GET, Request::METHOD_HEAD => ['activityId', 'agent', 'registration', 'stateId', 'since'],
            Request::METHOD_POST, Request::METHOD_PUT, Request::METHOD_DELETE => ['activityId', 'agent', 'registration', 'stateId'],
            default => [],
        };

        $unknownParameters = array_diff(array_keys($request->query->all()), $allowedParameters);
        if ([] !== $unknownParameters) {
            throw new BadRequestException(sprintf(
                'Unrecognized query parameter(s): "%s".',
                implode('", "', $unknownParameters)
            ));
        }

        if (
            $request->query->has('since')
            && $request->query->has('stateId')
        ) {
            throw new BadRequestException('The "since" parameter cannot be used with "stateId".');
        }
    }

    private function getContentType(Request $request): ?string
    {
        $contentType = $request->headers->get('Content-Type');

        return null === $contentType ? null : trim($contentType);
    }

    private function getMediaType(?string $contentType): string
    {
        return strtolower(trim(explode(';', $contentType ?? '', 2)[0]));
    }

    /**
     * @return Statement|Statement[]
     */
    public function deserializeStatement(Request $request): Statement|array
    {
        try {
            $content = $request->getContent() ?? '';
            $attachments = [];

            if ($this->isMultipartRequest($request)) {
                [$content, $attachments] = $this->extractMultipartStatement($content, $request->headers->get('Content-Type'));
            }

            if (str_starts_with(ltrim($content), '[')) {
                return $this->statementSerializer->deserializeStatements($content, $attachments);
            }

            return $this->statementSerializer->deserializeStatement($content, $attachments);
        } catch (UnsupportedStatementVersionException|InvalidArgumentException|DeserializationException|JsonException) {
            throw $this->createBadRequestException('statement');
        }
    }

    private function isMultipartRequest(Request $request): bool
    {
        $contentType = $this->getContentType($request);

        return null !== $contentType
            && str_starts_with(strtolower($contentType), 'multipart/')
            && preg_match('/(?:^|;)\s*boundary\s*=/i', $contentType) === 1;
    }

    /**
     * @return array{0: string, 1: array<string, array{content: string}>}
     * @throws BadRequestException
     */
    private function extractMultipartStatement(string $content, ?string $contentType): array
    {
        if (null === $contentType || !preg_match('/boundary=(?:"([^"]+)"|([^;]+))/', $contentType, $matches)) {
            throw new BadRequestException('Multipart requests must declare a valid boundary.');
        }

        $boundary = trim('' !== $matches[1] ? $matches[1] : ($matches[2] ?? ''), "\" ");
        if ('' === $boundary) {
            throw new BadRequestException('Multipart requests must declare a valid boundary.');
        }

        $parts = preg_split(
            '/(?:^|\r\n|\n|\r)--'.preg_quote($boundary, '/').'(--)?(?:\r\n|\n|\r|$)/',
            $content,
            -1,
            PREG_SPLIT_NO_EMPTY
        );

        if (!is_array($parts) || [] === $parts) {
            throw new BadRequestException('The multipart payload could not be parsed.');
        }

        $jsonContent = null;
        $attachments = [];

        foreach ($parts as $part) {
            if (!preg_match('/\r\n\r\n|\n\n|\r\r/', $part, $separator, PREG_OFFSET_CAPTURE)) {
                continue;
            }

            $separatorPosition = $separator[0][1];
            $separatorLength = strlen($separator[0][0]);
            $headers = substr($part, 0, $separatorPosition);
            $body = substr($part, $separatorPosition + $separatorLength);

            $headerLines = preg_split('/\r\n|\n|\r/', $headers, -1, PREG_SPLIT_NO_EMPTY);
            if (!is_array($headerLines)) {
                continue;
            }

            $headerMap = [];
            foreach ($headerLines as $headerLine) {
                [$name, $value] = array_pad(explode(':', $headerLine, 2), 2, '');
                $headerMap[strtolower(trim($name))] = trim($value);
            }

            $mediaType = strtolower($headerMap['content-type'] ?? '');
            $bodyTrimmed = ltrim($body);

            if (null === $jsonContent) {
                if (
                    ('' !== $mediaType && str_starts_with($mediaType, 'application/json'))
                    || preg_match('/\A[\[{]/', $bodyTrimmed) === 1
                ) {
                    $jsonContent = $bodyTrimmed;
                    continue;
                }

                throw new BadRequestException('The first multipart part must contain a JSON statement payload.');
            }

            $sha2 = strtolower(trim($headerMap['x-experience-api-hash'] ?? ''));
            if (1 !== preg_match('/\A[a-f0-9]{64}\z/', $sha2) || !hash_equals($sha2, hash('sha256', $body))) {
                throw new BadRequestException('A multipart attachment has an invalid or mismatched X-Experience-API-Hash header.');
            }

            $attachments[$sha2] = ['content' => $body];
        }

        if (null === $jsonContent) {
            throw new BadRequestException('The multipart request does not contain a JSON statement payload.');
        }

        return [$jsonContent, $attachments];
    }

    private function createBadRequestException(string $type): BadRequestException
    {
        return new BadRequestException(
            sprintf('The content of the request cannot be deserialized into a valid xAPI %s.', $type)
        );
    }
}
