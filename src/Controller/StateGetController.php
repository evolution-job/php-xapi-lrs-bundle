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
use XApi\LrsBundle\Response\JsonXapiResponse;
use XApi\Repository\Api\StateRepositoryInterface;

/**
 * @author Mathieu Boldo <mathieu.boldo@entrili.com>
 */
final readonly class StateGetController
{
    public function __construct(private StateRepositoryInterface $stateRepository) { }

    public function getState(Request $request, State $state): JsonXapiResponse
    {
        $foundState = $this->stateRepository->findState($state);

        $isHeadRequest = $request->isMethod(Request::METHOD_HEAD);

        if ($foundState instanceof State) {

            return new JsonXapiResponse($foundState->getData(), Response::HTTP_OK, isHeadRequest: $isHeadRequest);
        }

        if ($state->getStateId() !== null) {

            return new JsonXapiResponse(status: Response::HTTP_NOT_FOUND);
        }

        // List of available States
        $states = $this->stateRepository->findStates($state);

        if (!$states) {

            return new JsonXapiResponse(status: Response::HTTP_NOT_FOUND);
        }

        $stateIds = [];
        foreach ($states as $foundState) {
            $stateIds[] = $foundState->getStateId();
        }

        return new JsonXapiResponse(array_unique($stateIds), Response::HTTP_OK);
    }
}
