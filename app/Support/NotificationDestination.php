<?php

namespace App\Support;

class NotificationDestination
{
    public static function local(mixed $value): string
    {
        if (!is_string($value) || $value === '' || preg_match('/[\x00-\x20\\\\]/', rawurldecode($value))) return '/dashboard';
        if (str_starts_with($value,'/') && !str_starts_with(rawurldecode($value),'//')) return $value;
        $parts = parse_url($value); $base = parse_url(config('app.url'));
        if (!$parts || !$base || !in_array($parts['scheme'] ?? '',['http','https'],true) || isset($parts['user']) || isset($parts['pass'])
            || strcasecmp($parts['host'] ?? '',$base['host'] ?? '') !== 0 || ($parts['port'] ?? null) !== ($base['port'] ?? null)) return '/dashboard';
        $path = ($parts['path'] ?? '/').(isset($parts['query']) ? '?'.$parts['query'] : '').(isset($parts['fragment']) ? '#'.$parts['fragment'] : '');
        return str_starts_with(rawurldecode($path),'//') ? '/dashboard' : $path;
    }
}
