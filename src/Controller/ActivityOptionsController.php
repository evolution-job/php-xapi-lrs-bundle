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

use Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Xabbuh\XApi\Model\IRI;
use XApi\LrsBundle\Response\XapiJsonResponse;

/**
 * @author Mathieu Boldo <mathieu.boldo@entrili.com>
 */
final class ActivityOptionsController
{
    public function optionsActivity(Request $request): XapiJsonResponse
    {
        if (!$activityId = $request->query->all()['activityId'] ?? null) {
            throw new BadRequestHttpException('Required activityId parameter is missing.');
        }

        if (!is_string($activityId)) {
            throw new BadRequestHttpException('Required activityId parameter is not a string.');
        }

        try {
            IRI::fromString($activityId);
        } catch (Exception $exception) {
            throw new BadRequestHttpException(sprintf('Parameter activityId %s is not a valid IRI.', json_encode($activityId, JSON_THROW_ON_ERROR)), $exception);
        }

        return new XapiJsonResponse('', Response::HTTP_NO_CONTENT);
    }
}
