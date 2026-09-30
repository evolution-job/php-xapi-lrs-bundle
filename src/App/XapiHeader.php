<?php

declare(strict_types=1);

namespace XApi\LrsBundle\App;

/**
 * @author Mathieu Boldo <mathieu.boldo@entrili.com>
 */
final class XapiHeader
{
    public const array ALLOWED = ['Authorization', self::VERSION, 'Content-Type', 'Content-Length', 'If-Match', 'If-None-Match'];
    public const string DATE_FORMAT = 'Y-m-d\TH:i:s.v\Z';
    public const string CONSISTENT_THROUGH_HEADER = 'X-Experience-API-Consistent-Through';
    public const string VERSION = 'X-Experience-API-Version';
}