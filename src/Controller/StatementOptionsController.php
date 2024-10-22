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

final class StatementOptionsController
{
    public function optionsStatement(Request $request): Response
    {
        if (null === $statementId = $request->query->get('statementId')) {
            throw new BadRequestHttpException('Required statementId parameter is missing.');
        }

        try {
            StatementId::fromString($statementId);
        } catch (InvalidArgumentException $invalidArgumentException) {
            throw new BadRequestHttpException(sprintf('Parameter statementId ("%s") is not a valid UUID.', $statementId), $invalidArgumentException);
        }

        return new Response('', 204);
    }
}
