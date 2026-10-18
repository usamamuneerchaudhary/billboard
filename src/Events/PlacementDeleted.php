<?php

namespace Billboard\Events;

use Billboard\Placements\Placement;
use Illuminate\Foundation\Events\Dispatchable;

class PlacementDeleted
{
    use Dispatchable;

    /**
     * Create the event for a placement that was deleted.
     */
    public function __construct(public Placement $placement)
    {
    }
}
