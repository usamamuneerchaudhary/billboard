<?php

namespace Billboard\Http\Controllers\CP;

use Billboard\Placements\PlacementRepository;
use Statamic\Http\Controllers\CP\ActionController;

class PlacementActionController extends ActionController
{
    /**
     * Load the selected placements for a bulk action.
     */
    protected function getSelectedItems($items, $context)
    {
        $placements = app(PlacementRepository::class);

        return $items->map(fn ($handle) => $placements->find((string) $handle))->filter()->values();
    }
}
