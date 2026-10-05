<?php

/*
 * This file is part of the xAPI package.
 *
 * (c) Christian Flothmann <christian.flothmann@xabbuh.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace XApi\LrsBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Xabbuh\XApi\Model\State;
use XApi\LrsBundle\Response\JsonResponse;
use XApi\Repository\Api\StateRepositoryInterface;

/**
 * @author Mathieu Boldo <mathieu.boldo@entrili.com>
 */
final readonly class StateGetController
{
    public function __construct(private StateRepositoryInterface $stateRepository) { }

    public function getState(Request $request, State $state): JsonResponse
    {
        $foundState = $this->stateRepository->findState($state);

        $isHeadRequest = $request->isMethod(Request::METHOD_HEAD);

        if ($foundState instanceof State) {

            return new JsonResponse($foundState->getData(), Response::HTTP_OK, isHeadRequest: $isHeadRequest);
        }

        if ($state->getStateId() !== null) {

            return new JsonResponse(status: Response::HTTP_NOT_FOUND);
        }

        // List of available States
        $states = $this->stateRepository->findStates($state);

        $stateIds = [];
        foreach ($states as $foundState) {
            $stateIds[] = $foundState->getStateId();
        }

        return new JsonResponse(array_unique($stateIds), Response::HTTP_OK);
    }
}
