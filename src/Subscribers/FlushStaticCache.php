<?php

namespace Billboard\Subscribers;

use Billboard\Events\AdDeleted;
use Billboard\Events\AdSaved;
use Billboard\Events\PlacementDeleted;
use Billboard\Events\PlacementSaved;
use Billboard\Support\Settings;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Log;
use Statamic\Facades\StaticCache;
use Throwable;

class FlushStaticCache
{
    /**
     * Create the subscriber with addon settings.
     */
    public function __construct(private Settings $settings)
    {
    }

    /**
     * Register handlers for ad and placement save and delete events.
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            AdSaved::class => 'handle',
            AdDeleted::class => 'handle',
            PlacementSaved::class => 'handle',
            PlacementDeleted::class => 'handle',
        ];
    }

    /**
     * Clear the static cache when flushing is enabled.
     */
    public function handle(): void
    {
        if (! $this->settings->flushesStaticCache() || ! config('statamic.static_caching.strategy')) {
            return;
        }

        try {
            StaticCache::flush();
        } catch (Throwable $e) {
            Log::warning('Billboard could not clear the static cache: '.$e->getMessage());
        }
    }
}
