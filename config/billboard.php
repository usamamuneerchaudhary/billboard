<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Storage
    |--------------------------------------------------------------------------
    |
    | Ads and placements are stored as YAML files so they can be versioned
    | alongside the rest of your content. Each ad is one file named by its
    | ID and each placement is one file named by its handle.
    |
    */

    'storage' => [
        'ads' => base_path('content/billboard/ads'),
        'placements' => base_path('content/billboard/placements'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Images
    |--------------------------------------------------------------------------
    |
    | The asset container used by the image field on image ads. When left
    | empty, Billboard uses the first asset container on the site. If the
    | site has no asset containers, image ads fall back to an image URL.
    |
    */

    'assets' => [
        'container' => env('BILLBOARD_ASSET_CONTAINER'),
        'folder' => 'billboard',
    ],

    /*
    |--------------------------------------------------------------------------
    | Reporting
    |--------------------------------------------------------------------------
    |
    | Impressions and clicks are stored as daily totals per ad and placement
    | in a database table. No IP addresses, cookies or user identifiers are
    | stored. Tracking can be switched off in the add-on settings screen.
    |
    | "rate_limit" is the number of tracking requests allowed per minute
    | from a single visitor. The visitor's IP is used only as a short-lived
    | rate limiter key in your cache and is never written to the database.
    |
    */

    'tracking' => [
        'connection' => env('BILLBOARD_DB_CONNECTION'),
        'table' => 'billboard_stats',
        'rate_limit' => 120,
        'cap_cache_seconds' => 60,

        // Extra case-insensitive substrings that identify bots, merged with
        // the built-in list (crawlers, uptime monitors, link previewers).
        'bot_patterns' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Front end
    |--------------------------------------------------------------------------
    |
    | "mobile_breakpoint" is the viewport width in pixels below which a
    | visitor counts as mobile for device targeting.
    |
    | When "auto_assets" is true, the first placement rendered on a page also
    | outputs Billboard's script (and stylesheet, if enabled in settings).
    | Set it to false if you'd rather place {{ billboard:assets }} yourself.
    |
    */

    'frontend' => [
        'mobile_breakpoint' => 768,
        'auto_assets' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Content
    |--------------------------------------------------------------------------
    |
    | When "inject_sets" is true, Bard and Replicator fields on entries,
    | terms, and globals gain an Advertisement set. Leave it false and add
    | the set in the blueprint builder, or set billboard: false on a field
    | to keep the set off that field while injection is on.
    |
    */

    'content' => [
        'inject_sets' => false,
    ],

];
