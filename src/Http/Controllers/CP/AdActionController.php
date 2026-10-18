<?php

namespace Billboard\Http\Controllers\CP;

use Billboard\Ads\AdRepository;
use Statamic\Http\Controllers\CP\ActionController;

class AdActionController extends ActionController
{
    /**
     * Load the selected ads for a bulk action.
     */
    protected function getSelectedItems($items, $context)
    {
        $ads = app(AdRepository::class);

        return $items->map(fn ($id) => $ads->find((string) $id))->filter()->values();
    }
}
