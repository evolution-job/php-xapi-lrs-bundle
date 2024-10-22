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

final class StatePutController
{
    public function __construct(private readonly StateRepositoryInterface $stateRepository) {}

    public function putState(State $state): Response
    {
        $this->stateRepository->storeState($state);

        $response = new Response();
        $dateTime = new DateTime();
        $response->headers->set('X-Experience-API-Consistent-Through', $dateTime->format('Y-m-d\TH:i:sP'));

        return $response;
    }
}