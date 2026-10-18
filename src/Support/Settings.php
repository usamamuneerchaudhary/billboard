<?php

namespace Billboard\Support;

use Billboard\Billboard;
use Throwable;

class Settings
{
    public const array DEFAULTS = [
        'tracking_enabled' => true,
        'respect_privacy_signals' => true,
        'retention_days' => 395,
        'default_label' => 'Advertisement',
        'include_default_styles' => true,
        'flush_static_cache' => false,
    ];

    private ?array $resolved = null;

    /**
     * Return a setting, falling back to the default when it is unset.
     */
    public function get(string $key): mixed
    {
        $values = $this->resolve();

        return array_key_exists($key, $values) && $values[$key] !== null
            ? $values[$key]
            : (self::DEFAULTS[$key] ?? null);
    }

    /**
     * Return whether impression and click tracking is enabled.
     */
    public function trackingEnabled(): bool
    {
        return (bool) $this->get('tracking_enabled');
    }

    /**
     * Return whether privacy signals should suppress tracking.
     */
    public function respectsPrivacySignals(): bool
    {
        return (bool) $this->get('respect_privacy_signals');
    }

    /**
     * Return how many days of statistics to keep.
     */
    public function retentionDays(): int
    {
        return max(0, (int) $this->get('retention_days'));
    }

    /**
     * Return the default disclosure label.
     */
    public function defaultLabel(): string
    {
        return (string) $this->get('default_label');
    }

    /**
     * Return whether the default frontend stylesheet is included.
     */
    public function includesDefaultStyles(): bool
    {
        return (bool) $this->get('include_default_styles');
    }

    /**
     * Return whether saving ads or placements should flush the static cache.
     */
    public function flushesStaticCache(): bool
    {
        return (bool) $this->get('flush_static_cache');
    }

    /**
     * Forget the cached settings so the next read loads them again.
     */
    public function refresh(): void
    {
        $this->resolved = null;
    }

    /**
     * Load the saved addon settings, or an empty set when they cannot be read.
     */
    private function resolve(): array
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        try {
            $addon = Billboard::addon();

            return $this->resolved = $addon ? $addon->settings()->all() : [];
        } catch (Throwable) {
            return $this->resolved = [];
        }
    }
}
