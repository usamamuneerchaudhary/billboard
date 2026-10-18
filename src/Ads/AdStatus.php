<?php

namespace Billboard\Ads;

enum AdStatus: string
{
    case Active = 'active';
    case Scheduled = 'scheduled';
    case Expired = 'expired';
    case Disabled = 'disabled';

    /**
     * Return the translated label for this status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Active => __('Active'),
            self::Scheduled => __('Scheduled'),
            self::Expired => __('Expired'),
            self::Disabled => __('Disabled'),
        };
    }

    /**
     * Return the control panel color for this status.
     */
    public function color(): string
    {
        return match ($this) {
            self::Active => 'green',
            self::Scheduled => 'blue',
            self::Expired => 'amber',
            self::Disabled => 'default',
        };
    }
}
