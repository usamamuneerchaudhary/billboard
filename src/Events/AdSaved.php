<?php

namespace Billboard\Events;

use Billboard\Ads\Ad;
use Illuminate\Foundation\Events\Dispatchable;

class AdSaved
{
    use Dispatchable;

    /**
     * Create the event for an ad that was saved.
     */
    public function __construct(public Ad $ad)
    {
    }
}
