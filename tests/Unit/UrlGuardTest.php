<?php

namespace Billboard\Tests\Unit;

use Billboard\Support\UrlGuard;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class UrlGuardTest extends TestCase
{
    public static function urls(): array
    {
        return [
            'https' => ['https://example.com/landing', true],
            'http' => ['http://example.com', true],
            'uppercase scheme' => ['HTTPS://EXAMPLE.COM/x?y=1#z', true],
            'root relative' => ['/pricing', true],
            'trimmed' => [' https://example.com ', true],
            'protocol relative' => ['//evil.com', false],
            'javascript' => ['javascript:alert(1)', false],
            'mixed case javascript' => ['JavaScript:alert(1)', false],
            'data' => ['data:text/html,hi', false],
            'no host' => ['https:///path', false],
            'whitespace' => ['https://example.com/a b', false],
            'header injection' => ["/path\nLocation: x", false],
            'backslash' => ['/\\evil.com', false],
            'ftp' => ['ftp://example.com', false],
            'bare domain' => ['example.com', false],
            'empty' => ['', false],
        ];
    }

    #[Test]
    #[DataProvider('urls')]
    public function it_only_accepts_safe_destinations(string $url, bool $expected): void
    {
        $this->assertSame($expected, UrlGuard::isSafe($url));
    }

    #[Test]
    public function it_rejects_null_and_very_long_urls(): void
    {
        $this->assertFalse(UrlGuard::isSafe(null));
        $this->assertFalse(UrlGuard::isSafe('https://example.com/'.str_repeat('a', 2100)));
    }
}
