<?php

namespace Billboard\Tests\Unit;

use Billboard\Tracking\BotDetector;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class BotDetectorTest extends TestCase
{
    public static function agents(): array
    {
        return [
            'chrome' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0 Safari/537.36', false],
            'safari ios' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1', false],
            'firefox' => ['Mozilla/5.0 (Macintosh; Intel Mac OS X 14.0; rv:130.0) Gecko/20100101 Firefox/130.0', false],
            'googlebot' => ['Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)', true],
            'facebook' => ['facebookexternalhit/1.1', true],
            'headless chrome' => ['Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/120.0 Safari/537.36', true],
            'curl' => ['curl/8.5.0', true],
            'custom pattern' => ['Acme-Checker/1.0', true],
            'empty' => ['', true],
        ];
    }

    #[Test]
    #[DataProvider('agents')]
    public function it_identifies_bots(string $agent, bool $expected): void
    {
        $this->assertSame($expected, (new BotDetector(['acme-checker']))->isBot($agent));
    }
}
