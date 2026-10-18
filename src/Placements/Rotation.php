<?php

namespace Billboard\Placements;

enum Rotation: string
{
    case Weighted = 'weighted';
    case Ordered = 'ordered';

    /**
     * Return the translated label for this rotation mode.
     */
    public function label(): string
    {
        return match ($this) {
            self::Weighted => __('Random, by weight'),
            self::Ordered => __('In order of weight'),
        };
    }
}
