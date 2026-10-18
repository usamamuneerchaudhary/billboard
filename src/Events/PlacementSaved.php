<?php

namespace Billboard\Events;

use Billboard\Placements\Placement;
use Illuminate\Foundation\Events\Dispatchable;

class PlacementSaved
{
    use Dispatchable;

    /**
     * Create the event for a placement that was saved.
     */
    public function __construct(public Placement $placement)
    {
    }
}
