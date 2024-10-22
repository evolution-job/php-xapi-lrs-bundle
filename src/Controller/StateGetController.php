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

use DateTime;
use Symfony\Component\HttpFoundation\Response;
use Xabbuh\XApi\Model\State;
use XApi\Repository\Api\StateRepositoryInterface;
use XApi\Repository\Doctrine\Mapping\State as MappedState;

final class StateGetController
{
    public function __construct(private readonly StateRepositoryInterface $stateRepository) {}

    public function getState(State $state): Response
    {
        $mappedState = $this->stateRepository->findState([
            "stateId"        => $state->getStateId(),
            "activityId"     => $state->getActivity()->getId()->getValue(),
            "registrationId" => $state->getRegistrationId()
        ]);

        if ($mappedState instanceof MappedState) {
            $response = new Response($mappedState->data, Response::HTTP_OK, []);
        } else {
            $response = new Response('', Response::HTTP_NOT_FOUND, []);
        }

        $dateTime = new DateTime();
        $response->headers->set('X-Experience-API-Consistent-Through', $dateTime->format('Y-m-d\TH:i:sP'));

        return $response;
    }
}
