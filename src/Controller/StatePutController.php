<?php

declare(strict_types=1);

/*
 * This file is part of the xAPI package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace XApi\LrsBundle\Controller;

use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\PreconditionFailedHttpException;
use Xabbuh\XApi\Model\State;
use XApi\LrsBundle\Response\JsonResponse;
use XApi\LrsBundle\Response\StateDocumentResponse;
use XApi\Repository\Api\StateRepositoryInterface;


/**
 * @author Mathieu Boldo <mathieu.boldo@entrili.com>
 */
final readonly class StatePutController
{
    public function __construct(private StateRepositoryInterface $stateRepository) { }

    public function putState(State $state, ?Request $request = null): JsonResponse
    {
        $request ??= new Request();
        $ifMatch = $request->headers->get('If-Match');
        $ifNoneMatch = $request->headers->get('If-None-Match');

        if (null !== $ifMatch || null !== $ifNoneMatch) {
            $existingState = $this->stateRepository->findState($state);
            $etag = $existingState instanceof State
                ? (new StateDocumentResponse($existingState->getData(), contentType: $existingState->getContentType()))->headers->get('ETag')
                : null;

            if (
                (null !== $ifMatch && !$this->matchesIfMatch($ifMatch, $etag))
                || (null !== $ifNoneMatch && $this->matchesIfNoneMatch($ifNoneMatch, $etag))
            ) {
                throw new PreconditionFailedHttpException('The state document precondition failed.');
            }
        }

        $this->stateRepository->storeState($state);

        return new JsonResponse(status: Response::HTTP_NO_CONTENT);
    }

    private function matchesIfMatch(string $header, ?string $etag): bool
    {
        if (null === $etag) {
            return false;
        }

        $etag = substr($etag, 1, -1);
        return array_any($this->entityTags($header), fn(string $entityTag): bool => '*' === $entityTag || $etag === $entityTag);
    }

    private function matchesIfNoneMatch(string $header, ?string $etag): bool
    {
        if (null === $etag) {
            return false;
        }

        $etag = substr($etag, 1, -1);

        foreach ($this->entityTags($header) as $entityTag) {
            $weakEntityTag = str_starts_with($entityTag, 'W/') ? substr($entityTag, 2) : $entityTag;

            if ('*' === $entityTag || $etag === $weakEntityTag) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return string[]
     */
    private function entityTags(string $header): array
    {
        return array_map(
            static fn (string|array $parts): string => trim(is_array($parts) ? ($parts[0] ?? '') : $parts),
            HeaderUtils::split($header, ',')
        );
    }
}
