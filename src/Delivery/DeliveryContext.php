<?php

namespace Billboard\Delivery;

use Carbon\CarbonImmutable;
use Statamic\Facades\Site;
use Throwable;

final class DeliveryContext
{
    /**
     * Create a delivery context for a moment, site, and path.
     */
    public function __construct(
        public readonly CarbonImmutable $now,
        public readonly ?string $site,
        public readonly string $path,
    ) {}

    /**
     * Build a delivery context from the current request.
     */
    public static function fromRequest(): self
    {
        try {
            $site = Site::current()?->handle();
        } catch (Throwable) {
            $site = null;
        }

        return new self(CarbonImmutable::now(), $site, '/'.ltrim(request()->path(), '/'));
    }
}
