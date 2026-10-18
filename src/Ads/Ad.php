<?php

namespace Billboard\Ads;

use Billboard\Delivery\PathMatcher;
use Billboard\Support\UrlGuard;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Arr;
use Statamic\Facades\AssetContainer;
use Throwable;

class Ad
{
    public const array DEVICES = ['desktop', 'mobile'];

    /**
     * Create an ad from its id and stored values.
     *
     * @param  array<string, mixed>  $data
     */
    public function __construct(private string $id, private array $data = []) {}

    /**
     * Return the ad id.
     */
    public function id(): string
    {
        return $this->id;
    }

    /**
     * Return the ad title, or a placeholder when none is set.
     */
    public function title(): string
    {
        return (string) ($this->data['title'] ?? __('Untitled ad'));
    }

    /**
     * Return whether the ad is enabled.
     */
    public function enabled(): bool
    {
        return (bool) ($this->data['enabled'] ?? true);
    }

    /**
     * Return the ad type, defaulting to image.
     */
    public function type(): AdType
    {
        return AdType::tryFrom((string) ($this->data['type'] ?? '')) ?? AdType::Image;
    }

    /**
     * Return the destination URL when it is safe to use.
     */
    public function url(): ?string
    {
        $url = $this->data['url'] ?? null;

        return UrlGuard::isSafe($url) ? trim($url) : null;
    }

    /**
     * Return whether the destination opens in a new tab.
     */
    public function opensInNewTab(): bool
    {
        return (bool) ($this->data['new_tab'] ?? false);
    }

    /**
     * Return the image alternative text.
     */
    public function alt(): string
    {
        return (string) ($this->data['alt'] ?? '');
    }

    /**
     * Return the text ad headline.
     */
    public function headline(): string
    {
        return (string) ($this->data['headline'] ?? '');
    }

    /**
     * Return the text ad body.
     */
    public function body(): string
    {
        return (string) ($this->data['body'] ?? '');
    }

    /**
     * Return the text ad button label.
     */
    public function buttonText(): string
    {
        return (string) ($this->data['button_text'] ?? '');
    }

    /**
     * Return the raw HTML creative.
     */
    public function html(): string
    {
        return (string) ($this->data['html'] ?? '');
    }

    /**
     * Return the placement handles this ad is assigned to.
     *
     * @return array<int, string>
     */
    public function placements(): array
    {
        return array_values(array_filter(array_map('strval', Arr::wrap($this->data['placements'] ?? []))));
    }

    /**
     * Return the rotation weight, clamped between 1 and 100.
     */
    public function weight(): int
    {
        return min(100, max(1, (int) ($this->data['weight'] ?? 10)));
    }

    /**
     * Return the scheduled start time.
     */
    public function startsAt(): ?CarbonImmutable
    {
        return $this->date('start_at');
    }

    /**
     * Return the scheduled end time.
     */
    public function endsAt(): ?CarbonImmutable
    {
        return $this->date('end_at');
    }

    /**
     * Return the site handles this ad is limited to.
     *
     * @return array<int, string>
     */
    public function sites(): array
    {
        return array_values(array_filter(array_map('strval', Arr::wrap($this->data['sites'] ?? []))));
    }

    /**
     * Return the paths this ad is allowed to appear on.
     *
     * @return array<int, string>
     */
    public function includePaths(): array
    {
        return array_values(array_filter(array_map('trim', array_map('strval', Arr::wrap($this->data['include_paths'] ?? [])))));
    }

    /**
     * Return the paths this ad must not appear on.
     *
     * @return array<int, string>
     */
    public function excludePaths(): array
    {
        return array_values(array_filter(array_map('trim', array_map('strval', Arr::wrap($this->data['exclude_paths'] ?? [])))));
    }

    /**
     * Return the devices this ad targets.
     *
     * @return array<int, string> An empty array means every device.
     */
    public function devices(): array
    {
        $devices = array_values(array_intersect(self::DEVICES, Arr::wrap($this->data['devices'] ?? [])));

        return count($devices) === count(self::DEVICES) ? [] : $devices;
    }

    /**
     * Return the lifetime impression cap, if one is set.
     */
    public function maxImpressions(): ?int
    {
        return $this->positiveInt('max_impressions');
    }

    /**
     * Return the lifetime click cap, if one is set.
     */
    public function maxClicks(): ?int
    {
        return $this->positiveInt('max_clicks');
    }

    /**
     * Return the per-visitor daily view cap, if one is set.
     */
    public function frequencyCap(): ?int
    {
        return $this->positiveInt('frequency_cap');
    }

    /**
     * Return whether the ad has an impression or click cap.
     */
    public function hasCaps(): bool
    {
        return $this->maxImpressions() !== null || $this->maxClicks() !== null;
    }

    /**
     * Decide whether these lifetime totals are still under the ad's caps.
     *
     * @param  array{impressions?: int, clicks?: int}  $totals
     */
    public function withinCaps(array $totals): bool
    {
        $impressions = $totals['impressions'] ?? 0;
        $clicks = $totals['clicks'] ?? 0;

        if ($this->maxImpressions() !== null && $impressions >= $this->maxImpressions()) {
            return false;
        }

        return $this->maxClicks() === null || $clicks < $this->maxClicks();
    }

    /**
     * Return the current status from the schedule and enabled flag.
     */
    public function status(?CarbonInterface $now = null): AdStatus
    {
        $now ??= CarbonImmutable::now();

        return match (true) {
            ! $this->enabled() => AdStatus::Disabled,
            $this->hasEnded($now) => AdStatus::Expired,
            ! $this->hasStarted($now) => AdStatus::Scheduled,
            default => AdStatus::Active,
        };
    }

    /**
     * Decide whether the ad's start time has been reached.
     */
    public function hasStarted(CarbonInterface $now): bool
    {
        $start = $this->startsAt();

        return $start === null || $start->lessThanOrEqualTo($now);
    }

    /**
     * Decide whether the ad's end time has passed.
     */
    public function hasEnded(CarbonInterface $now): bool
    {
        $end = $this->endsAt();

        return $end !== null && $end->lessThanOrEqualTo($now);
    }

    /**
     * Decide whether the ad is allowed on the given site.
     */
    public function isAvailableOnSite(?string $site): bool
    {
        return $this->sites() === [] || $site === null || in_array($site, $this->sites(), true);
    }

    /**
     * Decide whether the path matches this ad's include and exclude rules.
     */
    public function matchesPath(string $path): bool
    {
        if ($this->excludePaths() !== [] && PathMatcher::matchesAny($path, $this->excludePaths())) {
            return false;
        }

        return $this->includePaths() === [] || PathMatcher::matchesAny($path, $this->includePaths());
    }

    /**
     * Resolve the image to display, or null if the ad has no usable image.
     *
     * @return array{src: string, width: int|null, height: int|null}|null
     */
    public function image(): ?array
    {
        $path = $this->data['image'] ?? null;

        if (is_array($path)) {
            $path = $path[0] ?? null;
        }

        if (is_string($path) && $path !== '') {
            try {
                $handle = config('billboard.assets.container');
                $container = $handle ? AssetContainer::find($handle) : AssetContainer::all()->first();

                if ($container && ($asset = $container->asset($path)) && ($src = $asset->url())) {
                    return [
                        'src' => $src,
                        'width' => $asset->width() ? (int) $asset->width() : null,
                        'height' => $asset->height() ? (int) $asset->height() : null,
                    ];
                }
            } catch (Throwable) {
                // Fall through to the URL field below.
            }
        }

        $url = $this->data['image_url'] ?? null;

        return UrlGuard::isSafe($url) ? ['src' => trim($url), 'width' => null, 'height' => null] : null;
    }

    /**
     * Decide whether the ad has the fields required to render its type.
     */
    public function isRenderable(): bool
    {
        return match ($this->type()) {
            AdType::Image => $this->image() !== null && $this->url() !== null,
            AdType::Text => $this->headline() !== '' && $this->url() !== null,
            AdType::Html => trim($this->html()) !== '',
        };
    }

    /**
     * Return the control panel URL for editing this ad.
     */
    public function editUrl(): string
    {
        return cp_route('billboard.ads.edit', $this->id);
    }

    /**
     * Return the reports URL filtered to this ad.
     */
    public function reportUrl(): string
    {
        return cp_route('billboard.reports.index', ['ad' => $this->id]);
    }

    /**
     * Return a stored value by key.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    /**
     * Merge values into the stored data and return this ad.
     *
     * @param  array<string, mixed>  $values
     */
    public function merge(array $values): self
    {
        $this->data = array_merge($this->data, $values);

        return $this;
    }

    /**
     * Replace the assigned placement handles and return this ad.
     *
     * @param  array<int, string>  $placements
     */
    public function setPlacements(array $placements): self
    {
        $this->data['placements'] = array_values($placements);

        return $this;
    }

    /**
     * Return the stored ad values.
     *
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return $this->data;
    }

    /**
     * Parse a stored date field into an immutable timestamp.
     */
    private function date(string $key): ?CarbonImmutable
    {
        $value = $this->data[$key] ?? null;

        if ($value === null || $value === '') {
            return null;
        }

        try {
            return is_numeric($value)
                ? CarbonImmutable::createFromTimestamp((int) $value, config('app.timezone'))
                : CarbonImmutable::parse((string) $value, config('app.timezone'));
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Return a stored positive integer, or null when it is missing or invalid.
     */
    private function positiveInt(string $key): ?int
    {
        $value = $this->data[$key] ?? null;

        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }
}
