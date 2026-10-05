<?php

/*
 * This file is part of the xAPI package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace XApi\LrsBundle\Controller;

use JsonException;
use stdClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Xabbuh\XApi\Common\Exception\BadRequestException;
use Xabbuh\XApi\Model\State;
use XApi\LrsBundle\Response\JsonResponse;
use XApi\Repository\Api\StateRepositoryInterface;

/**
 * @author Mathieu Boldo <mathieu.boldo@entrili.com>
 */
final readonly class StatePostController
{
    public function __construct(private StateRepositoryInterface $stateRepository) { }

    public function postState(State $state, Request $request): JsonResponse
    {
        $existingState = $this->stateRepository->findState($state);

        if ($existingState instanceof State) {
            $state = $this->mergeJsonDocument($existingState, $state, $request);
        }

        $this->stateRepository->storeState($state);

        return new JsonResponse(status: Response::HTTP_NO_CONTENT);
    }

    private function mergeJsonDocument(State $existingState, State $postedState, Request $request): State
    {
        $contentType = strtolower(trim(explode(';', $request->headers->get('Content-Type', ''), 2)[0]));
        $existingData = $existingState->getData();
        $existingContentType = $existingState->getContentType();

        if (
            'application/json' !== $contentType
            || null === $existingContentType
            || 'application/json' !== strtolower(trim(explode(';', $existingContentType, 2)[0]))
            || !is_array($existingData)
            || ([] !== $existingData && array_is_list($existingData))
        ) {
            throw new BadRequestException('POST can only merge an existing JSON object using application/json.');
        }

        try {
            $postedObject = json_decode($request->getContent(), false, 512, JSON_THROW_ON_ERROR);
            $postedData = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new BadRequestException('The posted document is not valid JSON.');
        }

        if (!$postedObject instanceof stdClass) {
            throw new BadRequestException('The posted document must be a JSON object.');
        }

        // xAPI State POST merges at the top level: posted keys replace existing
        // values, while omitted existing keys remain. Nested values are replaced whole.
        // https://github.com/adlnet/xAPI-Spec/blob/master/xAPI-Communication.md
        $mergedData = array_replace($existingData, $postedData);

        return new State(
            $postedState->getActivity(),
            $postedState->getAgent(),
            $postedState->getStateId(),
            $postedState->getRegistrationId(),
            $mergedData,
            $postedState->getContentType()
        );
    }
}
