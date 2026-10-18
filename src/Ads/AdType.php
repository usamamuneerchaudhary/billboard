<?php

namespace Billboard\Ads;

enum AdType: string
{
    case Image = 'image';
    case Text = 'text';
    case Html = 'html';

    /**
     * Return the translated label for this ad type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Image => __('Image'),
            self::Text => __('Text'),
            self::Html => __('HTML'),
        };
    }

    /**
     * Decide whether clicks on this ad type are tracked.
     */
    public function tracksClicks(): bool
    {
        return $this !== self::Html;
    }
}
