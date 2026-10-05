<?php

declare(strict_types=1);

/*
 * This file is part of the xAPI package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace XApi\LrsBundle\Service;

use DateTimeImmutable;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Xabbuh\XApi\Common\Exception\BadRequestException;
use Xabbuh\XApi\Model\Agent;
use Xabbuh\XApi\Model\InverseFunctionalIdentifier;
use Xabbuh\XApi\Serializer\ActorSerializerInterface;
use Xabbuh\XApi\Serializer\Exception\ActorDeserializationException;
use XApi\LrsBundle\App\IriValidator;
use XApi\LrsBundle\App\XapiTimestampParser;

/**
 * @author Mathieu Boldo <mathieu.boldo@entrili.com>
 */
final readonly class ProfileRequestParser
{
    public function __construct(private ActorSerializerInterface $actorSerializer) { }

    /**
     * @throws BadRequestException
     */
    public function activityResource(Request $request): string
    {
        $this->validate($request, ['activityId', 'profileId', 'since']);
        $activityId = $request->query->get('activityId');

        if (!is_string($activityId) || !IriValidator::isValid($activityId)) {
            throw new BadRequestException('Parameter "activityId" must be a valid IRI.');
        }

        return 'activity:'.$activityId;
    }

    /**
     * @throws BadRequestException
     */
    public function agentResource(Request $request): string
    {
        $this->validate($request, ['agent', 'profileId', 'since']);
        return 'agent:'.$this->agent($request)->getInverseFunctionalIdentifier();
    }

    /**
     * @throws BadRequestException
     */
    public function agent(Request $request): Agent
    {
        $agent = $request->query->get('agent');
        if (!is_string($agent)) {
            throw new BadRequestException('Required agent parameter is missing or invalid.');
        }

        try {
            $actor = $this->actorSerializer->deserializeActor($agent);
        } catch (ActorDeserializationException|InvalidArgumentException) {
            throw new BadRequestException('The agent parameter must be a valid xAPI Agent object.');
        }

        if (!$actor instanceof Agent || !$actor->getInverseFunctionalIdentifier() instanceof InverseFunctionalIdentifier) {
            throw new BadRequestException('The agent parameter must be a valid xAPI Agent object.');
        }

        return $actor;
    }

    /**
     * @throws BadRequestException
     */
    public function profileId(Request $request, bool $required): ?string
    {
        $profileId = $request->query->get('profileId');
        if (null === $profileId && !$required) {
            return null;
        }

        if (!is_string($profileId) || '' === $profileId) {
            throw new BadRequestException('Required profileId parameter is missing or invalid.');
        }

        return $profileId;
    }

    /**
     * @throws BadRequestException
     */
    public function since(Request $request): ?DateTimeImmutable
    {
        return $request->query->has('since')
            ? XapiTimestampParser::parse($request->query->get('since'), 'since')
            : null;
    }

    /**
     * @throws BadRequestException
     */
    private function validate(Request $request, array $allowed): void
    {
        $unknown = array_diff(array_keys($request->query->all()), $allowed);
        if ([] !== $unknown) {
            throw new BadRequestException(sprintf('Unrecognized query parameter(s): %s.', implode(', ', $unknown)));
        }

        if ($request->query->has('since') && $request->query->has('profileId')) {
            throw new BadRequestException('The "since" parameter cannot be used with "profileId".');
        }
    }
}
