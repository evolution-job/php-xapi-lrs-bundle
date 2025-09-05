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
final readonly class StateDeleteController
{
    public function __construct(private StateRepositoryInterface $stateRepository) { }

    public function deleteState(State $state): XapiJsonResponse
    {
        $foundState = $this->stateRepository->findState($state);

        if ($foundState instanceof State) {

            $this->stateRepository->removeState($state);
        }

        return new XapiJsonResponse('', Response::HTTP_NO_CONTENT);
    }
}
