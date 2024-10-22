<?php

/*
 * This file is part of the xAPI package.
 *
 * (c) Christian Flothmann <christian.flothmann@xabbuh.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace XApi\LrsBundle\Model;

use DateTime;
use DateTimeInterface;
use Symfony\Component\HttpFoundation\ParameterBag;
use Xabbuh\XApi\Model\Activity;
use Xabbuh\XApi\Model\IRI;
use Xabbuh\XApi\Model\StatementsFilter;
use Xabbuh\XApi\Model\Verb;
use Xabbuh\XApi\Serializer\ActorSerializerInterface;

/**
 * @author Jérôme Parmentier <jerome.parmentier@acensi.fr>
 */
class StatementsFilterFactory
{
    public function __construct(private readonly ActorSerializerInterface $actorSerializer) { }

    public function createFromParameterBag(ParameterBag $parameterBag): StatementsFilter
    {
        $statementsFilter = new StatementsFilter();

        if (($actor = $parameterBag->get('agent')) !== null) {
            $statementsFilter->byActor($this->actorSerializer->deserializeActor($actor));
        }

        if (($verbId = $parameterBag->get('verb')) !== null) {
            $statementsFilter->byVerb(new Verb(IRI::fromString($verbId)));
        }

        if (($activityId = $parameterBag->get('activity')) !== null) {
            $statementsFilter->byActivity(new Activity(IRI::fromString($activityId)));
        }

        if (($registration = $parameterBag->get('registration')) !== null) {
            $statementsFilter->byRegistration($registration);
        }

        if ($parameterBag->filter('related_activities', false, FILTER_VALIDATE_BOOLEAN)) {
            $statementsFilter->enableRelatedActivityFilter();
        } else {
            $statementsFilter->disableRelatedActivityFilter();
        }

        if ($parameterBag->filter('related_agents', false, FILTER_VALIDATE_BOOLEAN)) {
            $statementsFilter->enableRelatedAgentFilter();
        } else {
            $statementsFilter->disableRelatedAgentFilter();
        }

        if (($since = $parameterBag->get('since')) !== null) {
            $statementsFilter->since(DateTime::createFromFormat(DateTimeInterface::ATOM, $since));
        }

        if (($until = $parameterBag->get('until')) !== null) {
            $statementsFilter->until(DateTime::createFromFormat(DateTimeInterface::ATOM, $until));
        }

        if ($parameterBag->filter('ascending', false, FILTER_VALIDATE_BOOLEAN)) {
            $statementsFilter->ascending();
        } else {
            $statementsFilter->descending();
        }

        $statementsFilter->limit($parameterBag->getInt('limit'));

        return $statementsFilter;
    }
}
