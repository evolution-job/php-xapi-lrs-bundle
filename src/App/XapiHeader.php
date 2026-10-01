<?php

declare(strict_types=1);

namespace XApi\LrsBundle\App;

/**
 * @author Mathieu Boldo <mathieu.boldo@entrili.com>
 */
final class XapiHeader
{
    public const array ALLOWED = ['Accept', 'Authorization', 'Content-Type', 'If-Match', 'If-None-Match', self::VERSION];
    public const string ALLOWED_METHODS = 'GET, POST, PUT, DELETE, HEAD, OPTIONS';
    public const string CONSISTENT_THROUGH_HEADER = 'X-Experience-API-Consistent-Through';
    public const string DATE_FORMAT = 'Y-m-d\TH:i:s.v\Z';
    public const string EXPOSE_HEADERS = 'ETag, Last-Modified, X-Experience-API-Version, X-Experience-API-Consistent-Through';
    public const string VERSION = 'X-Experience-API-Version';
}