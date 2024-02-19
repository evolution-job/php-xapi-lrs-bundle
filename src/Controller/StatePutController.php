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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Xabbuh\XApi\Model\State;
use XApi\Repository\Api\StateRepositoryInterface;

final class StatePutController
{
    private $repository;

    /**
     * @param StateRepositoryInterface $repository
     */
    public function __construct(StateRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @param Request $request
     * @param State $state
     * @return Response
     */
    public function putState(Request $request, State $state): Response
    {
        $mappedState = $this->repository->findState([
            "stateId"      => $state->getStateId(),
            "activity"     => $state->getActivity()->getId()->getValue(),
            "registrationId" => $state->getRegistrationId()
        ]);

        if ($mappedState instanceof \XApi\Repository\Doctrine\Mapping\State) {
            $mappedState->data = json_encode($request->request->get('data'));
            $this->repository->updateState($mappedState);
        } else {
            $this->repository->storeState($state);
        }


        $response = new Response();
        $now = new DateTime();
        $response->headers->set('X-Experience-API-Consistent-Through', $now->format('Y-m-d\TH:i:sP'));

        return $response;
    }
}