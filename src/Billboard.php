<?php

namespace Billboard;

use Statamic\Addons\Addon as StatamicAddon;
use Statamic\Facades\Addon;

final class Billboard
{
    public const string NAMESPACE = 'Billboard';

    public const string SLUG = 'billboard';

    /**
     * Return the installed addon, matched by namespace so the Composer vendor can change.
     */
    public static function addon(): ?StatamicAddon
    {
        return Addon::all()->first(fn ($addon) => $addon->namespace() === self::NAMESPACE);
    }

    /**
     * Resolve a path inside this package, or the package root when none is given.
     */
    public static function packagePath(string $path = ''): string
    {
        return dirname(__DIR__).($path !== '' ? '/'.ltrim($path, '/') : '');
    }
}
