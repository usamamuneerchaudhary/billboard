<?php

namespace Billboard\Delivery;

final class PathMatcher
{
    /**
     * Normalize a path to a leading slash without a trailing slash.
     */
    public static function normalize(string $path): string
    {
        $path = parse_url($path, PHP_URL_PATH) ?? '/';
        $path = '/'.trim($path, '/');

        return $path === '/' ? '/' : rtrim($path, '/');
    }

    /**
     * Decide whether a path matches any of the given patterns.
     *
     * @param  array<int, string>  $patterns
     */
    public static function matchesAny(string $path, array $patterns): bool
    {
        $path = self::normalize($path);

        foreach ($patterns as $pattern) {
            if (self::matches($path, (string) $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Decide whether a path matches a single wildcard pattern.
     */
    public static function matches(string $path, string $pattern): bool
    {
        $pattern = trim($pattern);

        if ($pattern === '') {
            return false;
        }

        if ($pattern === '*') {
            return true;
        }

        $pattern = self::normalize($pattern);
        $path = self::normalize($path);

        // "/blog/*" also matches "/blog" itself.
        if (str_ends_with($pattern, '/*') && $path === substr($pattern, 0, -2)) {
            return true;
        }

        $regex = '#^'.str_replace('\*', '.*', preg_quote($pattern, '#')).'$#i';

        return (bool) preg_match($regex, $path);
    }
}
