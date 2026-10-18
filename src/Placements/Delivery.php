<?php

namespace Billboard\Placements;

enum Delivery: string
{
    case Server = 'server';
    case Client = 'client';

    /**
     * Return the translated label for this delivery mode.
     */
    public function label(): string
    {
        return match ($this) {
            self::Server => __('On the server'),
            self::Client => __('In the browser'),
        };
    }
}
