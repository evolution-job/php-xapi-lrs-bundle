<?php

declare(strict_types=1);

/*
 * This file is part of the xAPI package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace XApi\LrsBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Xabbuh\XApi\Common\Exception\BadRequestException;
use Xabbuh\XApi\Model\Account;
use Xabbuh\XApi\Model\IRI;
use XApi\LrsBundle\Response\JsonResponse;
use XApi\LrsBundle\Service\ProfileRequestParser;

/**
 * @author Mathieu Boldo <mathieu.boldo@entrili.com>
 */
final readonly class AgentGetController
{
    public function __construct(
        private ProfileRequestParser $profileRequestParser
    ) { }

    /**
     * @throws BadRequestException
     */
    public function getAgent(Request $request): JsonResponse
    {
        $unknown = array_diff(array_keys($request->query->all()), ['agent']);
        if ([] !== $unknown) {
            throw new BadRequestException(sprintf('Unrecognized query parameter(s): %s.', implode(', ', $unknown)));
        }

        $agent = $this->profileRequestParser->agent($request);
        $identifier = $agent->getInverseFunctionalIdentifier();
        $person = [];

        if (null !== $agent->getName()) {
            $person['name'] = [$agent->getName()];
        }

        if ($identifier->getMbox() instanceof IRI) {
            $person['mbox'] = [$identifier->getMbox()->getValue()];
        }

        if (null !== $identifier->getMboxSha1Sum()) {
            $person['mbox_sha1sum'] = [$identifier->getMboxSha1Sum()];
        }

        if (null !== $identifier->getOpenId()) {
            $person['openid'] = [$identifier->getOpenId()];
        }

        if ($identifier->getAccount() instanceof Account) {
            $person['account'] = [[
                'homePage' => $identifier->getAccount()->getHomePage()->getValue(),
                'name' => $identifier->getAccount()->getName(),
            ]];
        }

        return new JsonResponse(
            $person,
            Response::HTTP_OK,
            isHeadRequest: $request->isMethod(Request::METHOD_HEAD)
        );
    }
}
