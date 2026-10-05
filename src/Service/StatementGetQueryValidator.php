<?php

/*
 * This file is part of the xAPI package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace XApi\LrsBundle\Service;

use DateMalformedStringException;
use DateTimeImmutable;
use InvalidArgumentException as PhpInvalidArgumentException;
use Symfony\Component\HttpFoundation\ParameterBag;
use Xabbuh\XApi\Common\Exception\BadRequestException;
use Xabbuh\XApi\Model\StatementId;
use Xabbuh\XApi\Model\StatementsFilter;
use Xabbuh\XApi\Model\Uuid;
use XApi\LrsBundle\App\IriValidator;
use XApi\LrsBundle\App\XapiTimestampParser;
use XApi\LrsBundle\Model\StatementsFilterFactory;

/**
 * @author Mathieu Boldo <mathieu.boldo@entrili.com>
 */
final readonly class StatementGetQueryValidator
{
    private const int SERVER_LIMIT = 100;

    private const array ALLOWED_PARAMETERS = [
        'activity'           => true,
        'agent'              => true,
        'ascending'          => true,
        'attachments'        => true,
        'format'             => true,
        'limit'              => true,
        'offset'             => true,
        'registration'       => true,
        'related_activities' => true,
        'related_agents'     => true,
        'since'              => true,
        'statementId'        => true,
        'until'              => true,
        'verb'               => true,
        'voidedStatementId'  => true,
    ];

    public function __construct(private StatementsFilterFactory $statementsFilterFactory) { }

    /**
     * @throws BadRequestException
     */
    public function validate(ParameterBag $parameterBag): void
    {
        $unknownParameters = array_diff(array_keys($parameterBag->all()), array_keys(self::ALLOWED_PARAMETERS));
        if ([] !== $unknownParameters) {
            throw new BadRequestException(sprintf('Unrecognized query parameter(s): "%s".', implode('", "', $unknownParameters)));
        }

        $format = $parameterBag->get('format', 'exact');
        if (!in_array($format, ['ids', 'exact', 'canonical'], true)) {
            throw new BadRequestException('The format parameter must be one of "ids", "exact", or "canonical".');
        }

        foreach (['statementId', 'voidedStatementId'] as $parameter) {
            if (!$parameterBag->has($parameter)) {
                continue;
            }

            $value = $parameterBag->get($parameter);
            if (!is_string($value)) {
                throw new BadRequestException(sprintf('Parameter "%s" must be a UUID string.', $parameter));
            }

            try {
                StatementId::fromString($value);
            } catch (PhpInvalidArgumentException) {
                throw new BadRequestException(sprintf('Parameter "%s" must be a valid UUID.', $parameter));
            }
        }

        foreach (['activity', 'verb', 'agent', 'registration'] as $parameter) {
            if (!$parameterBag->has($parameter)) {
                continue;
            }

            $value = $parameterBag->get($parameter);
            if (!is_string($value) || '' === $value) {
                throw new BadRequestException(sprintf('Parameter "%s" must be a non-empty string.', $parameter));
            }

            if (in_array($parameter, ['activity', 'verb'], true) && !IriValidator::isValid($value)) {
                throw new BadRequestException(sprintf('Parameter "%s" must be a valid IRI.', $parameter));
            }

            if ('registration' === $parameter) {
                try {
                    Uuid::fromString($value);
                } catch (PhpInvalidArgumentException) {
                    throw new BadRequestException('Parameter "registration" must be a valid UUID.');
                }
            }
        }

        foreach (['ascending', 'attachments', 'related_activities', 'related_agents'] as $parameter) {
            if (!$parameterBag->has($parameter)) {
                continue;
            }

            $value = $parameterBag->get($parameter);
            if (!in_array($value, ['true', 'false', true, false], true)) {
                throw new BadRequestException(sprintf('Parameter "%s" must be either "true" or "false".', $parameter));
            }
        }

        $hasStatementId = $parameterBag->has('statementId');
        $hasVoidedStatementId = $parameterBag->has('voidedStatementId');

        foreach (['limit', 'offset'] as $parameter) {
            if (!$parameterBag->has($parameter)) {
                continue;
            }

            $value = $parameterBag->get($parameter);
            $validated = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
            if (false === $validated || ('offset' === $parameter && PHP_INT_MAX - self::SERVER_LIMIT - 1 < $validated)) {
                throw new BadRequestException(sprintf('Parameter "%s" must be a non-negative integer.', $parameter));
            }
        }

        if ($hasStatementId && $hasVoidedStatementId) {
            throw new BadRequestException('Request must not have both statementId and voidedStatementId parameters at the same time.');
        }

        $queryParameters = $parameterBag->all();
        unset(
            $queryParameters['attachments'],
            $queryParameters['format'],
            $queryParameters['statementId'],
            $queryParameters['voidedStatementId'],
        );

        if (($hasStatementId || $hasVoidedStatementId) && count($queryParameters)) {
            $badParameters = implode('", "', array_keys($queryParameters));

            throw new BadRequestException(sprintf('Request must not contain statementId or voidedStatementId parameters, and also any other parameter like "%s" besides "attachments" or "format".', $badParameters));
        }
    }

    /**
     * @throws DateMalformedStringException
     */
    public function createStatementsFilter(ParameterBag $parameterBag, int $limit, ?DateTimeImmutable $until): StatementsFilter
    {
        $filterQuery = clone $parameterBag;
        $filterQuery->set('limit', $limit);

        if ($until instanceof DateTimeImmutable && $this->hasNonZeroFraction($parameterBag->get('until'))) {
            $filterQuery->set('until', $until->modify('+1 second')->format('c'));
        }

        return $this->statementsFilterFactory->createFromParameterBag($filterQuery);
    }

    public function parseTimestamp(mixed $value, string $parameter): DateTimeImmutable
    {
        return XapiTimestampParser::parse($value, $parameter);
    }

    private function hasNonZeroFraction(mixed $value): bool
    {
        return is_string($value)
            && 1 === preg_match('/\.(\d+)(?:Z|[+-]\d{2}:\d{2})\z/', $value, $matches)
            && '' !== trim($matches[1], '0');
    }
}
