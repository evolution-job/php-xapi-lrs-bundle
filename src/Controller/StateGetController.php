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

use Symfony\Component\HttpFoundation\Response;
use Xabbuh\XApi\Model\State;
use XApi\LrsBundle\Response\XapiJsonResponse;
use XApi\Repository\Api\StateRepositoryInterface;

/**
 * @author Mathieu Boldo <mathieu.boldo@entrili.com>
 */
final readonly class StateGetController
{
    public function __construct(private StateRepositoryInterface $stateRepository) { }

    public function getState(State $state): XapiJsonResponse
    {
        $foundState = $this->stateRepository->findState($state);

        if ($foundState instanceof State) {

            return new XapiJsonResponse($foundState->getData(), Response::HTTP_OK);
        }

        if ($state->getStateId() !== null) {

            return new XapiJsonResponse('', Response::HTTP_NOT_FOUND);
        }

        // List of available States
        $states = $this->stateRepository->findStates($state);

        if (!$states) {

            return new XapiJsonResponse('', Response::HTTP_NOT_FOUND);
        }

        $stateIds = [];
        foreach ($states as $foundState) {
            $stateIds[] = $foundState->getStateId();
        }

        return new XapiJsonResponse(array_unique($stateIds), Response::HTTP_NOT_FOUND);
    }
}
