<?php

declare(strict_types=1);

/*
 * This file is part of the xAPI package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace XApi\LrsBundle\Service;

use DateTimeImmutable;
use JsonException;
use stdClass;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\PreconditionFailedHttpException;
use Xabbuh\XApi\Common\Exception\BadRequestException;
use Xabbuh\XApi\Model\ProfileDocument;
use XApi\LrsBundle\Response\JsonResponse;
use XApi\LrsBundle\Response\StateDocumentResponse;
use XApi\Repository\Api\ProfileRepositoryInterface;

/**
 * @author Mathieu Boldo <mathieu.boldo@entrili.com>
 */
final readonly class ProfileService
{
    public function __construct(
        private ProfileRepositoryInterface $profileRepository,
        private ProfileRequestParser $profileRequestParser
    ) { }

    /**
     * @throws BadRequestException
     * @throws JsonException
     */
    public function getActivityProfile(Request $request): JsonResponse|StateDocumentResponse
    {
        return $this->get($request, $this->profileRequestParser->activityResource($request));
    }

    /**
     * @throws BadRequestException
     * @throws JsonException
     */
    public function getAgentProfile(Request $request): JsonResponse|StateDocumentResponse
    {
        return $this->get($request, $this->profileRequestParser->agentResource($request));
    }

    /**
     * @throws BadRequestException
     * @throws JsonException
     */
    public function putActivityProfile(Request $request): JsonResponse
    {
        return $this->put($request, $this->profileRequestParser->activityResource($request));
    }

    /**
     * @throws BadRequestException|JsonException
     */
    public function putAgentProfile(Request $request): JsonResponse
    {
        return $this->put($request, $this->profileRequestParser->agentResource($request));
    }

    /**
     * @throws BadRequestException|JsonException
     */
    public function postActivityProfile(Request $request): JsonResponse
    {
        return $this->post($request, $this->profileRequestParser->activityResource($request));
    }

    /**
     * @throws BadRequestException|JsonException
     */
    public function postAgentProfile(Request $request): JsonResponse
    {
        return $this->post($request, $this->profileRequestParser->agentResource($request));
    }

    /**
     * @throws BadRequestException
     */
    public function deleteActivityProfile(Request $request): JsonResponse
    {
        return $this->delete($request, $this->profileRequestParser->activityResource($request));
    }

    /**
     * @throws BadRequestException
     */
    public function deleteAgentProfile(Request $request): JsonResponse
    {
        return $this->delete($request, $this->profileRequestParser->agentResource($request));
    }

    /**
     * @throws BadRequestException
     * @throws JsonException
     */
    private function get(Request $request, string $resource): JsonResponse|StateDocumentResponse
    {
        $profileId = $this->profileRequestParser->profileId($request, false);
        if (null === $profileId) {
            return new JsonResponse(
                $this->profileRepository->findIds($resource, $this->profileRequestParser->since($request)),
                isHeadRequest: $request->isMethod(Request::METHOD_HEAD)
            );
        }

        $document = $this->profileRepository->find($resource, $profileId);
        if (!$document instanceof ProfileDocument) {
            return new JsonResponse(status: Response::HTTP_NOT_FOUND);
        }

        return new StateDocumentResponse(
            $document->content,
            isHeadRequest: $request->isMethod(Request::METHOD_HEAD),
            contentType: $document->contentType
        );
    }

    /**
     * @throws BadRequestException
     * @throws JsonException
     */
    private function put(Request $request, string $resource): JsonResponse
    {
        $profileId = $this->profileRequestParser->profileId($request, true);
        $existing = $this->profileRepository->find($resource, $profileId);
        $this->assertPrecondition($request, $existing);
        $this->profileRepository->store($resource, $profileId, $this->documentFromRequest($request));

        return new JsonResponse(status: Response::HTTP_NO_CONTENT);
    }

    /**
     * @throws BadRequestException|JsonException
     */
    private function post(Request $request, string $resource): JsonResponse
    {
        $profileId = $this->profileRequestParser->profileId($request, true);
        $existing = $this->profileRepository->find($resource, $profileId);
        $this->assertPrecondition($request, $existing);
        $document = $this->documentFromRequest($request);

        if ($existing instanceof ProfileDocument) {
            $document = $this->mergedDocument($existing, $document);
        } else {
            $this->assertJsonObject($document->content);
        }

        $this->profileRepository->store($resource, $profileId, $document);

        return new JsonResponse(status: Response::HTTP_NO_CONTENT);
    }

    /**
     * @throws BadRequestException
     */
    private function delete(Request $request, string $resource): JsonResponse
    {
        $this->profileRepository->remove($resource, $this->profileRequestParser->profileId($request, false));

        return new JsonResponse(status: Response::HTTP_NO_CONTENT);
    }

    /**
     * @throws BadRequestException
     */
    private function documentFromRequest(Request $request): ProfileDocument
    {
        $contentType = trim((string) $request->headers->get('Content-Type'));
        if ('' === $contentType) {
            throw new BadRequestException('A Content-Type header is required for profile documents.');
        }

        return new ProfileDocument($request->getContent(), $contentType, new DateTimeImmutable());
    }

    /**
     * @throws BadRequestException
     */
    private function mergedDocument(ProfileDocument $existing, ProfileDocument $posted): ProfileDocument
    {
        if ('application/json' !== $this->mediaType($existing->contentType) || 'application/json' !== $this->mediaType($posted->contentType)) {
            throw new BadRequestException('POST can only merge JSON profile documents.');
        }

        $existingData = $this->jsonObject($existing->content);
        $postedData = $this->jsonObject($posted->content);

        try {
            $content = json_encode(array_replace($existingData, $postedData), JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new BadRequestException('The posted document is not valid JSON.');
        }

        return new ProfileDocument($content, $posted->contentType, $posted->updated);
    }

    /**
     * @throws BadRequestException
     */
    private function assertJsonObject(string $content): void
    {
        $this->jsonObject($content);
    }

    /**
     * @return array<string, mixed>
     * @throws BadRequestException
     */
    private function jsonObject(string $content): array
    {
        try {
            $object = json_decode($content, false, 512, JSON_THROW_ON_ERROR);
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new BadRequestException('The posted document is not valid JSON.');
        }

        if (!$object instanceof stdClass || !is_array($data)) {
            throw new BadRequestException('The posted document must be a JSON object.');
        }

        return $data;
    }

    /**
     * @throws JsonException
     */
    private function assertPrecondition(Request $request, ?ProfileDocument $profileDocument): void
    {
        $ifMatch = $request->headers->get('If-Match');
        $ifNoneMatch = $request->headers->get('If-None-Match');
        if (null === $ifMatch && null === $ifNoneMatch) {
            return;
        }

        $etag = $profileDocument instanceof ProfileDocument ? $this->etag($profileDocument) : null;
        if ((null !== $ifMatch && !$this->matches($ifMatch, $etag, true)) || (null !== $ifNoneMatch && $this->matches($ifNoneMatch, $etag, false))) {
            throw new PreconditionFailedHttpException('The profile document precondition failed.');
        }
    }

    /**
     * @throws JsonException
     */
    private function etag(ProfileDocument $profileDocument): string
    {
        return new StateDocumentResponse($profileDocument->content, contentType: $profileDocument->contentType)->headers->get('ETag') ?? '';
    }

    private function matches(string $header, ?string $etag, bool $strong): bool
    {
        if (null === $etag) {
            return false;
        }

        $etag = substr($etag, 1, -1);
        foreach (HeaderUtils::split($header, ',') as $part) {
            $tag = trim(is_array($part) ? ($part[0] ?? '') : $part);
            if ('*' === $tag) {
                return true;
            }

            if (!$strong && str_starts_with($tag, 'W/')) {
                $tag = substr($tag, 2);
            }

            if ($tag !== $etag || ($strong && str_starts_with($tag, 'W/'))) {
                continue;
            }

            return true;
        }

        return false;
    }

    private function mediaType(string $contentType): string
    {
        return strtolower(trim(explode(';', $contentType, 2)[0]));
    }
}
