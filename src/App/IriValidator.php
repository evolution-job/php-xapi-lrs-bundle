<?php

namespace XApi\LrsBundle\App;

final class IriValidator
{
    public static function isValid(string $value): bool
    {
        return 1 === preg_match('/\A[A-Za-z][A-Za-z0-9+.-]*:.+/u', $value)
            && 0 === preg_match('/[\s\x00-\x1F\x7F]/u', $value)
            && 0 === preg_match('/[<>"{}|\\\\^`]/u', $value)
            && 0 === preg_match('/%(?![A-Fa-f0-9]{2})/', $value)
            && false !== parse_url($value);
    }
}
