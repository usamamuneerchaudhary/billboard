<?php

namespace Billboard\Events;

use Billboard\Ads\Ad;
use Illuminate\Foundation\Events\Dispatchable;

class AdDeleted
{
    use Dispatchable;

    /**
     * Create the event for an ad that was deleted.
     */
    public function __construct(public Ad $ad) {}
}
