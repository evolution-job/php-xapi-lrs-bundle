<?php

/*
 * This file is part of the xAPI package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace XApi\LrsBundle\Service;

use JsonException;
use Symfony\Component\HttpFoundation\Request;
use Xabbuh\XApi\Common\Exception\NotFoundException;
use Xabbuh\XApi\Model\Activity;
use Xabbuh\XApi\Model\IRI;
use Xabbuh\XApi\Model\LanguageMap;
use Xabbuh\XApi\Model\Verb;
use Xabbuh\XApi\Serializer\ActivitySerializerInterface;
use XApi\Repository\Api\ActivityRepositoryInterface;
use XApi\Repository\Api\VerbRepositoryInterface;

/**
 * @author Mathieu Boldo <mathieu.boldo@entrili.com>
 */
final readonly class StatementFormatNormalizer
{
    public function __construct(
        private ActivityRepositoryInterface $activityRepository,
        private VerbRepositoryInterface $verbRepository,
        private ActivitySerializerInterface $activitySerializer
    ) { }

    /**
     * @throws JsonException
     */
    public function normalize(string $json, string $format, Request $request, bool $isResult = false): string
    {
        if ('exact' === $format) {
            return $json;
        }

        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        if ('ids' === $format) {
            if ($isResult) {
                foreach ($data['statements'] as &$statement) {
                    $this->reduceStatementToIds($statement);
                }

                unset($statement);
            } else {
                $this->reduceStatementToIds($data);
            }

            return json_encode($data, JSON_THROW_ON_ERROR);
        }

        $activities = [];
        $verbs = [];

        if ($isResult) {
            foreach ($data['statements'] as &$statement) {
                $this->canonicalizeStatement($statement, $request, $activities, $verbs);
            }

            unset($statement);
        } else {
            $this->canonicalizeStatement($data, $request, $activities, $verbs);
        }

        return json_encode($data, JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<string, mixed> $statement
     * @param array<string, array<string, mixed>> $activities
     * @param array<string, array<string, mixed>> $verbs
     * @throws JsonException
     */
    private function canonicalizeStatement(
        array &$statement,
        Request $request,
        array &$activities,
        array &$verbs
    ): void {
        if (isset($statement['verb']['id']) && is_string($statement['verb']['id'])) {
            $verbId = $statement['verb']['id'];
            if (!isset($verbs[$verbId])) {
                try {
                    $verb = $this->verbRepository->findVerbById(IRI::fromString($verbId));
                    if (!$verb instanceof Verb) {
                        $verbs[$verbId] = $statement['verb'];
                    } else {
                        $canonicalVerb = ['id' => $verb->getId()->getValue()];
                        if (($display = $verb->getDisplay()) instanceof LanguageMap) {
                            $canonicalVerb['display'] = [];
                            foreach ($display->languageTags() as $languageTag) {
                                $canonicalVerb['display'][$languageTag] = $display[$languageTag];
                            }
                        }

                        $verbs[$verbId] = $canonicalVerb;
                    }
                } catch (NotFoundException) {
                    $verbs[$verbId] = $statement['verb'];
                }
            }

            $statement['verb'] = $verbs[$verbId];
            if (isset($statement['verb']['display']) && is_array($statement['verb']['display'])) {
                $this->filterLanguageMap($statement['verb']['display'], $request);
            }
        }

        if (isset($statement['object']) && is_array($statement['object'])) {
            $this->canonicalizeStatementObject($statement['object'], $request, $activities, $verbs);
        }

        if (isset($statement['context']['contextActivities']) && is_array($statement['context']['contextActivities'])) {
            foreach ($statement['context']['contextActivities'] as &$contextActivities) {
                foreach ($contextActivities as &$activity) {
                    if (is_array($activity)) {
                        $this->canonicalizeActivity($activity, $request, $activities);
                    }
                }

                unset($activity);
            }

            unset($contextActivities);
        }
    }

    /**
     * @param array<string, mixed> $object
     * @param array<string, array<string, mixed>> $activities
     * @param array<string, array<string, mixed>> $verbs
     * @throws JsonException
     */
    private function canonicalizeStatementObject(
        array &$object,
        Request $request,
        array &$activities,
        array &$verbs
    ): void {
        $objectType = $object['objectType'] ?? 'Activity';
        if ('Activity' === $objectType) {
            $this->canonicalizeActivity($object, $request, $activities);

            return;
        }

        if ('SubStatement' === $objectType) {
            $this->canonicalizeStatement($object, $request, $activities, $verbs);
        }
    }

    /**
     * @param array<string, mixed> $activityData
     * @param array<string, array<string, mixed>> $activities
     * @throws JsonException
     */
    private function canonicalizeActivity(array &$activityData, Request $request, array &$activities): void
    {
        if (!isset($activityData['id']) || !is_string($activityData['id'])) {
            return;
        }

        $activityId = $activityData['id'];
        if (!isset($activities[$activityId])) {
            try {
                $activity = $this->activityRepository->findActivityById(IRI::fromString($activityId));
                if ($activity instanceof Activity) {
                    $activities[$activityId] = json_decode(
                        $this->activitySerializer->serializeActivity($activity),
                        true,
                        512,
                        JSON_THROW_ON_ERROR
                    );
                } else {
                    $activities[$activityId] = $activityData;
                }
            } catch (NotFoundException) {
                $activities[$activityId] = $activityData;
            }
        }

        $activityData = $activities[$activityId];
        $definition = &$activityData['definition'];
        if (is_array($definition)) {
            foreach (['name', 'description'] as $property) {
                if (isset($definition[$property]) && is_array($definition[$property])) {
                    $this->filterLanguageMap($definition[$property], $request);
                }
            }

            foreach (['choices', 'scale', 'source', 'target', 'steps'] as $componentType) {
                if (!isset($definition[$componentType]) || !is_array($definition[$componentType])) {
                    continue;
                }

                foreach ($definition[$componentType] as &$component) {
                    if (isset($component['description']) && is_array($component['description'])) {
                        $this->filterLanguageMap($component['description'], $request);
                    }
                }

                unset($component);
            }
        }

        unset($definition);
    }

    /**
     * @param array<string, string> $languageMap
     */
    private function filterLanguageMap(array &$languageMap, Request $request): void
    {
        if (count($languageMap) < 2) {
            return;
        }

        $language = $request->getPreferredLanguage(array_keys($languageMap));
        $language ??= array_key_first($languageMap);
        $value = $languageMap[$language];
        $languageMap = [$language => $value];
    }

    /**
     * @param array<string, mixed> $actor
     */
    private function reduceActorToIds(array &$actor): void
    {
        $identifier = null;
        foreach (['mbox', 'mbox_sha1sum', 'openid', 'account'] as $key) {
            if (array_key_exists($key, $actor)) {
                $identifier = [$key => $actor[$key]];
                break;
            }
        }

        $objectType = $actor['objectType'] ?? null;
        $members = $actor['member'] ?? [];
        $actor = $identifier ?? [];

        if (null !== $objectType) {
            $actor['objectType'] = $objectType;
        }

        if ('Group' === $objectType && null === $identifier && [] !== $members) {
            foreach ($members as &$member) {
                $this->reduceActorToIds($member);
            }

            unset($member);
            $actor['member'] = $members;
        }
    }

    /**
     * @param array<string, mixed> $object
     */
    private function reduceStatementObjectToIds(array &$object): void
    {
        $objectType = $object['objectType'] ?? 'Activity';

        if ('Activity' === $objectType) {
            unset($object['definition']);

            return;
        }

        if (in_array($objectType, ['Agent', 'Group'], true)) {
            $this->reduceActorToIds($object);
        }
    }

    /**
     * @param array<string, mixed> $statement
     */
    private function reduceStatementToIds(array &$statement): void
    {
        if (isset($statement['actor'])) {
            $this->reduceActorToIds($statement['actor']);
        }

        if (isset($statement['verb'])) {
            unset($statement['verb']['display']);
        }

        if (isset($statement['object'])) {
            $this->reduceStatementObjectToIds($statement['object']);
        }

        if (isset($statement['authority'])) {
            $this->reduceActorToIds($statement['authority']);
        }

        if (isset($statement['context'])) {
            foreach (['instructor', 'team'] as $actorProperty) {
                if (isset($statement['context'][$actorProperty])) {
                    $this->reduceActorToIds($statement['context'][$actorProperty]);
                }
            }

            if (isset($statement['context']['contextActivities'])) {
                foreach ($statement['context']['contextActivities'] as &$activities) {
                    foreach ($activities as &$activity) {
                        unset($activity['definition']);
                    }

                    unset($activity);
                }

                unset($activities);
            }
        }

        if (isset($statement['object']['objectType']) && 'SubStatement' === $statement['object']['objectType']) {
            $this->reduceStatementToIds($statement['object']);
        }
    }
}
