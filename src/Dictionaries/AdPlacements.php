<?php

namespace Billboard\Dictionaries;

use Billboard\Placements\Placement;
use Billboard\Placements\PlacementRepository;
use Statamic\Dictionaries\BasicDictionary;

class AdPlacements extends BasicDictionary
{
    protected static $handle = 'ad_placements';

    protected array $searchable = ['label', 'value', 'description'];

    /**
     * Return the dictionary title shown in the control panel.
     */
    public static function title()
    {
        return __('Ad placements');
    }

    /**
     * Return every placement as a dictionary option.
     */
    protected function getItems(): array
    {
        return app(PlacementRepository::class)->all()
            ->map(fn (Placement $placement) => [
                'value' => $placement->handle(),
                'label' => $placement->title(),
                'description' => $placement->description(),
            ])
            ->values()
            ->all();
    }
}
