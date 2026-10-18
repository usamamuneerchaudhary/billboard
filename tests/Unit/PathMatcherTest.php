<?php

namespace Billboard\Tests\Unit;

use Billboard\Delivery\PathMatcher;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class PathMatcherTest extends TestCase
{
    public static function cases(): array
    {
        return [
            ['/blog/post', '/blog/*', true],
            ['/blog', '/blog/*', true],
            ['/blog/', '/blog/*', true],
            ['/blogger', '/blog/*', false],
            ['/about', '/about', true],
            ['/about/', '/about', true],
            ['/ABOUT', '/about', true],
            ['/about/team', '/about', false],
            ['/anything', '*', true],
            ['/', '/', true],
            ['/shop/cat/item', '/shop/*/item', true],
            ['/blog/post?x=1', '/blog/post', true],
            ['/x(y)', '/x(y)', true],
            ['/path', '', false],
        ];
    }

    #[Test]
    #[DataProvider('cases')]
    public function it_matches_paths(string $path, string $pattern, bool $expected): void
    {
        $this->assertSame($expected, PathMatcher::matches($path, $pattern));
    }

    #[Test]
    public function it_matches_any_of_several_patterns(): void
    {
        $this->assertTrue(PathMatcher::matchesAny('/news/1', ['/blog/*', '/news/*']));
        $this->assertFalse(PathMatcher::matchesAny('/shop', ['/blog/*', '/news/*']));
    }
}
