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

use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Xabbuh\XApi\Model\StatementId;
use XApi\LrsBundle\Response\XapiJsonResponse;

/**
 * @author Mathieu Boldo <mathieu.boldo@entrili.com>
 */
final class StatementOptionsController
{
    public function optionsStatement(Request $request): XapiJsonResponse
    {
        if (!$statementId = $request->query->all()['statementId'] ?? null) {
            throw new BadRequestHttpException('Required statementId parameter is missing.');
        }

        if (!is_string($statementId)) {
            throw new BadRequestHttpException('Required statementId parameter is not a string.');
        }

        try {
            StatementId::fromString($statementId);
        } catch (InvalidArgumentException $invalidArgumentException) {
            throw new BadRequestHttpException(sprintf('Parameter statementId ("%s") is not a valid UUID.', $statementId), $invalidArgumentException);
        }

        return new XapiJsonResponse('', Response::HTTP_NO_CONTENT);
    }
}
