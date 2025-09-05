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
use Xabbuh\XApi\Model\State;
use XApi\LrsBundle\Response\XapiJsonResponse;
use XApi\Repository\Api\StateRepositoryInterface;

/**
 * @author Mathieu Boldo <mathieu.boldo@entrili.com>
 */
final readonly class StatePostController
{
    public function __construct(private StateRepositoryInterface $stateRepository) { }

    public function postState(State $state): XapiJsonResponse
    {
        $this->stateRepository->storeState($state);

        $response = new XapiJsonResponse();
        $dateTime = new DateTime();
        $response->headers->set('X-Experience-API-Consistent-Through', $dateTime->format('Y-m-d\TH:i:sP'));

        return $response;
    }
}