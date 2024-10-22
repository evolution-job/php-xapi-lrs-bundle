<?php

namespace spec\XApi\LrsBundle\Model;

use DateTime;
use PhpSpec\ObjectBehavior;
use Symfony\Component\HttpFoundation\ParameterBag;
use Xabbuh\XApi\DataFixtures\ActorFixtures;
use Xabbuh\XApi\DataFixtures\UuidFixtures;
use Xabbuh\XApi\Model\StatementsFilter;
use Xabbuh\XApi\Serializer\ActorSerializerInterface;
use XApi\Fixtures\Json\ActorJsonFixtures;

class StatementsFilterFactorySpec extends ObjectBehavior
{
    public function let(ActorSerializerInterface $actorSerializer): void
    {
        $this->beConstructedWith($actorSerializer);
    }

    public function it_sets_default_filter_when_parameters_are_empty(): void
    {
        $filter = $this->createFromParameterBag(new ParameterBag())->getFilter();

        $filter->shouldNotHaveKey('agent');
        $filter->shouldNotHaveKey('verb');
        $filter->shouldNotHaveKey('activity');
        $filter->shouldNotHaveKey('registration');
        $filter->shouldNotHaveKey('since');
        $filter->shouldNotHaveKey('until');
        $filter->shouldHaveKeyWithValue('related_activities', 'false');
        $filter->shouldHaveKeyWithValue('related_agents', 'false');
        $filter->shouldHaveKeyWithValue('ascending', 'false');
        $filter->shouldHaveKeyWithValue('limit', 0);
    }

    public function it_sets_an_agent_filter(ActorSerializerInterface $actorSerializer): void
    {
        $json = ActorJsonFixtures::getTypicalAgent();
        $actor = ActorFixtures::getTypicalAgent();

        $actorSerializer->deserializeActor($json)->shouldBeCalled()->willReturn($actor);

        $this->beConstructedWith($actorSerializer);

        $parameterBag = new ParameterBag();
        $parameterBag->set('agent', $json);

        /** @var StatementsFilter $filter */
        $filter = $this->createFromParameterBag($parameterBag);

        $filter->getFilter()->shouldHaveKeyWithValue('agent', $actor);
    }

    public function it_sets_a_verb_filter(): void
    {
        $verbId = 'http://tincanapi.com/conformancetest/verbid';
        $parameterBag = new ParameterBag();
        $parameterBag->set('verb', $verbId);

        /** @var StatementsFilter $filter */
        $filter = $this->createFromParameterBag($parameterBag);

        $filter->getFilter()->shouldHaveKeyWithValue('verb', $verbId);
    }

    public function it_sets_an_activity_filter(): void
    {
        $activityId = 'http://tincanapi.com/conformancetest/activityid';
        $parameterBag = new ParameterBag();
        $parameterBag->set('activity', $activityId);

        /** @var StatementsFilter $filter */
        $filter = $this->createFromParameterBag($parameterBag);

        $filter->getFilter()->shouldHaveKeyWithValue('activity', $activityId);
    }

    public function it_sets_a_registration_filter(): void
    {
        $registration = UuidFixtures::getGoodUuid();
        $parameterBag = new ParameterBag();
        $parameterBag->set('registration', $registration);

        /** @var StatementsFilter $filter */
        $filter = $this->createFromParameterBag($parameterBag);

        $filter->getFilter()->shouldHaveKeyWithValue('registration', $registration);
    }

    public function it_sets_a_related_activities_filter(): void
    {
        $parameterBag = new ParameterBag();
        $parameterBag->set('related_activities', true);

        /** @var StatementsFilter $filter */
        $filter = $this->createFromParameterBag($parameterBag);

        $filter->getFilter()->shouldHaveKeyWithValue('related_activities', 'true');
    }

    public function it_sets_a_related_agents_filter(): void
    {
        $parameterBag = new ParameterBag();
        $parameterBag->set('related_agents', true);

        /** @var StatementsFilter $filter */
        $filter = $this->createFromParameterBag($parameterBag);

        $filter->getFilter()->shouldHaveKeyWithValue('related_agents', 'true');
    }

    public function it_sets_a_since_filter(): void
    {
        $dateTime = new DateTime();

        $parameterBag = new ParameterBag();
        $parameterBag->set('since', $dateTime->format(DateTime::ATOM));

        /** @var StatementsFilter $filter */
        $filter = $this->createFromParameterBag($parameterBag);

        $filter->getFilter()->shouldHaveKeyWithValue('since', $dateTime->format('c'));
    }

    public function it_sets_an_until_filter(): void
    {
        $dateTime = new DateTime();

        $parameterBag = new ParameterBag();
        $parameterBag->set('until', $dateTime->format(DateTime::ATOM));

        /** @var StatementsFilter $filter */
        $filter = $this->createFromParameterBag($parameterBag);

        $filter->getFilter()->shouldHaveKeyWithValue('until', $dateTime->format('c'));
    }

    public function it_sets_an_ascending_filter(): void
    {
        $parameterBag = new ParameterBag();
        $parameterBag->set('ascending', true);

        /** @var StatementsFilter $filter */
        $filter = $this->createFromParameterBag($parameterBag);

        $filter->getFilter()->shouldHaveKeyWithValue('ascending', 'true');
    }

    public function it_sets_a_limit_filter(): void
    {
        $parameterBag = new ParameterBag();
        $parameterBag->set('limit', 10);

        /** @var StatementsFilter $filter */
        $filter = $this->createFromParameterBag($parameterBag);

        $filter->getFilter()->shouldHaveKeyWithValue('limit', 10);
    }
}
