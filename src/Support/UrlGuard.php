<?php

namespace Billboard\Support;

final class UrlGuard
{
    /**
     * Decide whether a URL is safe to use as an ad destination.
     */
    public static function isSafe(?string $url): bool
    {
        if ($url === null) {
            return false;
        }

        $url = trim($url);

        if ($url === '' || strlen($url) > 2048) {
            return false;
        }

        if (preg_match('/[\x00-\x20\x7F\\\\]/', $url)) {
            return false;
        }

        if (str_starts_with($url, '/')) {
            return ! str_starts_with($url, '//');
        }

        if (! preg_match('#^https?://#i', $url)) {
            return false;
        }

        $parts = parse_url($url);

        return is_array($parts)
            && isset($parts['host'])
            && $parts['host'] !== ''
            && in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true);
    }
}
