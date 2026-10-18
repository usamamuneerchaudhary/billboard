<?php

namespace Billboard\Tracking;

final class BotDetector
{
    private const array PATTERNS = [
        'bot', 'crawl', 'spider', 'slurp', 'mediapartners', 'facebookexternalhit', 'embedly',
        'quora link preview', 'skypeuripreview', 'headlesschrome', 'phantomjs', 'puppeteer',
        'playwright', 'selenium', 'chrome-lighthouse', 'pagespeed', 'gtmetrix', 'pingdom',
        'uptimerobot', 'statuscake', 'curl/', 'wget/', 'python-requests', 'python-urllib',
        'go-http-client', 'java/', 'okhttp', 'axios/', 'node-fetch', 'libwww', 'httpclient',
        'scrapy', 'feedfetcher',
    ];

    /**
     * Create the detector with extra user-agent fragments to treat as bots.
     *
     * @param  array<int, string>  $extraPatterns
     */
    public function __construct(private array $extraPatterns = []) {}

    /**
     * Decide whether a user agent looks like a bot or automated client.
     */
    public function isBot(?string $userAgent): bool
    {
        $userAgent = strtolower(trim((string) $userAgent));

        if ($userAgent === '') {
            return true;
        }

        foreach ([...self::PATTERNS, ...$this->extraPatterns] as $pattern) {
            $pattern = strtolower(trim((string) $pattern));

            if ($pattern !== '' && str_contains($userAgent, $pattern)) {
                return true;
            }
        }

        return false;
    }
}
