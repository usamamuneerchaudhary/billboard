<?php

namespace Billboard\Delivery;

use Billboard\Ads\Ad;
use Billboard\Ads\AdType;
use Billboard\Billboard;
use Billboard\Placements\Delivery;
use Billboard\Placements\Placement;
use Billboard\Placements\PlacementRepository;
use Billboard\Support\Settings;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Statamic\Facades\Blink;

class Renderer
{
    public const string CLICK_MACRO = '%%CLICK_URL%%';

    /**
     * Create the renderer with placement, selection, and settings dependencies.
     */
    public function __construct(
        private PlacementRepository $placements,
        private AdSelector $selector,
        private Settings $settings,
    ) {}

    /**
     * Render a placement by handle. Returns an empty string when the placement
     * doesn't exist or has nothing to show, so templates never break.
     */
    public function placement(string|Placement|null $placement, ?DeliveryContext $context = null): string
    {
        $placement = $this->resolvePlacement($placement);

        if (! $placement) {
            return '';
        }

        $data = $this->placementData($placement, $context ?? DeliveryContext::fromRequest());

        if ($data['ads'] === [] && $data['fallback'] === null) {
            return '';
        }

        return $this->withAssets(view('billboard::placement', $data)->render());
    }

    /**
     * Render a single ad outside of any placement.
     */
    public function ad(?Ad $ad, ?DeliveryContext $context = null): string
    {
        $context ??= DeliveryContext::fromRequest();

        if (! $ad || ! $ad->enabled() || ! $ad->hasStarted($context->now) || $ad->hasEnded($context->now)
            || ! $ad->isAvailableOnSite($context->site) || ! $ad->isRenderable()) {
            return '';
        }

        $placement = Placement::direct();

        return $this->withAssets(view('billboard::placement', [
            ...$this->placementData($placement, $context, collect([$ad])),
            'label' => null,
        ])->render());
    }

    /**
     * The data behind a placement, also exposed to tag pairs for custom markup.
     *
     * @param  Collection<int, Ad>|null  $ads  Use these ads instead of selecting.
     * @return array<string, mixed>
     */
    public function placementData(Placement $placement, DeliveryContext $context, ?Collection $ads = null): array
    {
        $client = $placement->delivery() === Delivery::Client;

        if ($ads === null) {
            $candidates = $this->selector->candidates($placement, $context);
            $ads = $client ? AdSelector::ordered($candidates) : $this->selector->choose($placement, $candidates);
        }

        $style = collect([
            'width' => $placement->width(),
            'height' => $placement->height(),
        ])->filter()->map(fn (int $value, string $key) => "--billboard-{$key}: {$value}px")->implode('; ');

        return [
            'handle' => $placement->handle(),
            'title' => $placement->title(),
            'mode' => $placement->delivery()->value,
            'rotation' => $placement->rotation()->value,
            'max' => $placement->maxAds(),
            'width' => $placement->width(),
            'height' => $placement->height(),
            'style' => $style,
            'label' => $placement->resolveLabel($this->settings->defaultLabel()),
            'aria_label' => $placement->resolveLabel($this->settings->defaultLabel()) ?? __('Advertisement'),
            'fallback' => $placement->fallback(),
            'ads' => $ads->map(fn (Ad $ad) => $this->adData($ad, $placement))->values()->all(),
        ];
    }

    /**
     * Return the view data for one ad inside a placement.
     *
     * @return array<string, mixed>
     */
    public function adData(Ad $ad, Placement $placement): array
    {
        $clickUrl = $ad->url() ? $this->clickUrl($ad, $placement) : null;
        $image = $ad->type() === AdType::Image ? $ad->image() : null;

        $attributes = [
            'data-billboard-ad' => $ad->id(),
            'data-billboard-weight' => $ad->weight(),
            'data-billboard-start' => $ad->startsAt()?->getTimestamp(),
            'data-billboard-end' => $ad->endsAt()?->getTimestamp(),
            'data-billboard-devices' => $ad->devices() === [] ? null : implode(' ', $ad->devices()),
            'data-billboard-cap' => $ad->frequencyCap(),
        ];

        return [
            'id' => $ad->id(),
            'title' => $ad->title(),
            'type' => $ad->type()->value,
            'url' => $clickUrl,
            'destination' => $ad->url(),
            'target' => $ad->opensInNewTab() ? '_blank' : null,
            'rel' => $ad->opensInNewTab() ? 'sponsored noopener' : 'sponsored',
            'image' => $image ? [...$image, 'alt' => $ad->alt()] : null,
            'headline' => $ad->headline(),
            'body' => $ad->body(),
            'button_text' => $ad->buttonText(),
            'html' => $ad->type() === AdType::Html ? $this->expandMacros($ad->html(), $clickUrl) : null,
            'weight' => $ad->weight(),
            'attributes' => $this->attributes($attributes),
        ];
    }

    /**
     * Build the tracked click URL for an ad in a placement.
     */
    public function clickUrl(Ad $ad, Placement $placement): string
    {
        return route('statamic.billboard.click', ['ad' => $ad->id(), 'placement' => $placement->handle()], false);
    }

    /**
     * The script and stylesheet tags, output at most once per request.
     *
     * Pass $force after a discarded render so the tags still reach the response.
     */
    public function assets(bool $force = false): string
    {
        if (! $force && Blink::get('billboard:assets-rendered')) {
            return '';
        }

        Blink::put('billboard:assets-rendered', true);

        return view('billboard::assets', [
            'script' => $this->assetUrl('billboard.js'),
            'stylesheet' => $this->settings->includesDefaultStyles() ? $this->assetUrl('billboard.css') : null,
            'endpoint' => route('statamic.billboard.impressions', [], false),
            'tracking' => $this->settings->trackingEnabled(),
            'respect_privacy' => $this->settings->respectsPrivacySignals(),
            'breakpoint' => (int) config('billboard.frontend.mobile_breakpoint', 768),
        ])->render();
    }

    /**
     * Load a placement from a handle, or return the instance already given.
     */
    public function resolvePlacement(string|Placement|null $placement): ?Placement
    {
        if ($placement instanceof Placement) {
            return $placement;
        }

        if ($placement === null || $placement === '') {
            return null;
        }

        $found = $this->placements->find($placement);

        if (! $found && config('app.debug')) {
            Log::debug("Billboard placement [{$placement}] does not exist.");
        }

        return $found;
    }

    /**
     * Append frontend assets to rendered HTML when auto assets are enabled.
     */
    private function withAssets(string $html): string
    {
        return config('billboard.frontend.auto_assets', true) ? $html.$this->assets() : $html;
    }

    /**
     * Replace the click macro in HTML with the tracked click URL.
     */
    private function expandMacros(string $html, ?string $clickUrl): string
    {
        return str_replace(self::CLICK_MACRO, e($clickUrl ?? '#'), $html);
    }

    /**
     * Build an HTML attribute string from name and value pairs.
     *
     * @param  array<string, scalar|null>  $attributes
     */
    private function attributes(array $attributes): string
    {
        return collect($attributes)
            ->reject(fn ($value) => $value === null || $value === '')
            ->map(fn ($value, string $name) => $name.'="'.e((string) $value).'"')
            ->implode(' ');
    }

    /**
     * Return a versioned public URL for a published frontend asset.
     */
    private function assetUrl(string $file): string
    {
        $source = Billboard::packagePath('resources/dist/frontend/'.$file);
        $version = is_file($source) ? substr(md5((string) filemtime($source)), 0, 8) : '1';

        return asset('vendor/'.Billboard::SLUG.'/frontend/'.$file).'?v='.$version;
    }
}
