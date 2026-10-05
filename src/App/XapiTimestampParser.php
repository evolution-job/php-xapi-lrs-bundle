<?php

namespace XApi\LrsBundle\App;

use DateTimeImmutable;
use XApi\LrsBundle\Exception\BadRequestHttpException;

/**
 * @author Mathieu Boldo <mathieu.boldo@entrili.com>
 */
final class XapiTimestampParser
{
    public static function parse(mixed $value, string $parameter): DateTimeImmutable
    {
        if (!is_string($value) || 1 !== preg_match(
            '/\A(?<date>\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2})(?:\.(?<fraction>\d+))?(?<timezone>Z|[+-]\d{2}:\d{2})\z/',
            $value,
            $matches
        )) {
            throw new BadRequestHttpException(sprintf('Parameter "%s" must be a valid ISO 8601 timestamp.', $parameter));
        }

        $fraction = str_pad(substr($matches['fraction'] ?? '0', 0, 6), 6, '0');
        $timezone = 'Z' === $matches['timezone'] ? '+00:00' : $matches['timezone'];
        $timestamp = DateTimeImmutable::createFromFormat(
            '!Y-m-d\TH:i:s.uP',
            $matches['date'].'.'.$fraction.$timezone
        );
        $errors = DateTimeImmutable::getLastErrors();

        if (false === $timestamp || (false !== $errors && (0 !== $errors['warning_count'] || 0 !== $errors['error_count']))) {
            throw new BadRequestHttpException(sprintf('Parameter "%s" must be a valid ISO 8601 timestamp.', $parameter));
        }

        return $timestamp;
    }
}
