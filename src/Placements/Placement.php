<?php

namespace Billboard\Placements;

class Placement
{
    /**
     * Used for ads rendered on their own with {{ billboard:ad }}.
     * Handles must start with a letter, so this can never collide.
     */
    public const string DIRECT = '_direct';

    public const string HIDDEN_LABEL = '-';

    /**
     * Create a placement from its handle and stored values.
     *
     * @param  array<string, mixed>  $data
     */
    public function __construct(private string $handle, private array $data = []) {}

    /**
     * Build the synthetic placement used when an ad is rendered on its own.
     */
    public static function direct(): self
    {
        return new self(self::DIRECT, ['title' => __('Direct'), 'max_ads' => 1]);
    }

    /**
     * Return the placement handle.
     */
    public function handle(): string
    {
        return $this->handle;
    }

    /**
     * Decide whether this is the synthetic direct placement.
     */
    public function isDirect(): bool
    {
        return $this->handle === self::DIRECT;
    }

    /**
     * Return the placement title, falling back to the handle.
     */
    public function title(): string
    {
        return (string) ($this->data['title'] ?? $this->handle);
    }

    /**
     * Return the placement description.
     */
    public function description(): string
    {
        return (string) ($this->data['description'] ?? '');
    }

    /**
     * Return where ads are chosen, defaulting to the server.
     */
    public function delivery(): Delivery
    {
        return Delivery::tryFrom((string) ($this->data['delivery'] ?? '')) ?? Delivery::Server;
    }

    /**
     * Return the rotation mode, defaulting to weighted.
     */
    public function rotation(): Rotation
    {
        return Rotation::tryFrom((string) ($this->data['rotation'] ?? '')) ?? Rotation::Weighted;
    }

    /**
     * Return how many ads to show at once, clamped between 1 and 10.
     */
    public function maxAds(): int
    {
        return min(10, max(1, (int) ($this->data['max_ads'] ?? 1)));
    }

    /**
     * Return the reserved width in pixels.
     */
    public function width(): ?int
    {
        $value = $this->data['width'] ?? null;

        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }

    /**
     * Return the reserved height in pixels.
     */
    public function height(): ?int
    {
        $value = $this->data['height'] ?? null;

        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }

    /**
     * The label override: null means "use the default", "-" hides it.
     */
    public function labelOverride(): ?string
    {
        $label = trim((string) ($this->data['label'] ?? ''));

        return $label === '' ? null : $label;
    }

    /**
     * Return the disclosure label to show, or null when it should be hidden.
     */
    public function resolveLabel(string $default): ?string
    {
        $label = $this->labelOverride() ?? trim($default);

        return $label === '' || $label === self::HIDDEN_LABEL ? null : $label;
    }

    /**
     * Return the fallback HTML, or null when none is set.
     */
    public function fallback(): ?string
    {
        $fallback = $this->data['fallback'] ?? null;

        if (is_array($fallback)) {
            $fallback = $fallback['code'] ?? null;
        }

        if (! is_string($fallback)) {
            return null;
        }

        $fallback = trim($fallback);

        return $fallback === '' ? null : $fallback;
    }

    /**
     * Return the control panel URL for editing this placement.
     */
    public function editUrl(): string
    {
        return cp_route('billboard.placements.edit', $this->handle);
    }

    /**
     * Return the Antlers tag that renders this placement.
     */
    public function tag(): string
    {
        return '{{ billboard:'.$this->handle.' }}';
    }

    /**
     * Merge values into the stored data and return this placement.
     *
     * @param  array<string, mixed>  $values
     */
    public function merge(array $values): self
    {
        $this->data = array_merge($this->data, $values);

        return $this;
    }

    /**
     * Return the stored placement values.
     *
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return $this->data;
    }
}
