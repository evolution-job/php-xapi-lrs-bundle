<?php

/*
 * This file is part of the xAPI package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace XApi\LrsBundle\EventListener;

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

final readonly class XapiRequestDeserializer
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

            if (str_starts_with(ltrim($content), '[')) {
                return $this->statementSerializer->deserializeStatements($content);
            }

            return $this->statementSerializer->deserializeStatement($content);
        } catch (UnsupportedStatementVersionException|InvalidArgumentException|DeserializationException|JsonException) {
            throw $this->createBadRequestException('statement');
        }
    }

    private function createBadRequestException(string $type): BadRequestException
    {
        return new BadRequestException(
            sprintf('The content of the request cannot be deserialized into a valid xAPI %s.', $type)
        );
    }
}
