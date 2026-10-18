<?php

namespace Billboard\Delivery;

use Billboard\Ads\Ad;
use Billboard\Ads\AdRepository;
use Billboard\Placements\Delivery;
use Billboard\Placements\Placement;
use Billboard\Placements\Rotation;
use Billboard\Tracking\StatsRepository;
use Illuminate\Support\Collection;

class AdSelector
{
    /**
     * Create the selector with ad and stats repositories.
     */
    public function __construct(private AdRepository $ads, private StatsRepository $stats) {}

    /**
     * Ads that may appear in the placement.
     *
     * For placements that choose in the browser, ads scheduled to start later
     * are included (the browser checks the start time), so a statically cached
     * page starts showing them on time.
     *
     * @return Collection<int, Ad>
     */
    public function candidates(Placement $placement, DeliveryContext $context, ?bool $includeUpcoming = null): Collection
    {
        $includeUpcoming ??= $placement->delivery() === Delivery::Client;

        $ads = $this->ads->all()
            ->filter(fn (Ad $ad) => $ad->enabled())
            ->filter(fn (Ad $ad) => in_array($placement->handle(), $ad->placements(), true))
            ->filter(fn (Ad $ad) => ! $ad->hasEnded($context->now))
            ->filter(fn (Ad $ad) => $includeUpcoming || $ad->hasStarted($context->now))
            ->filter(fn (Ad $ad) => $ad->isAvailableOnSite($context->site))
            ->filter(fn (Ad $ad) => $ad->matchesPath($context->path))
            ->filter(fn (Ad $ad) => $ad->isRenderable());

        return $this->withinCaps($ads)->values();
    }

    /**
     * Choose which candidates to show, respecting the placement's rotation.
     *
     * @param  Collection<int, Ad>  $candidates
     * @return Collection<int, Ad>
     */
    public function choose(Placement $placement, Collection $candidates, ?callable $random = null): Collection
    {
        if ($placement->rotation() === Rotation::Ordered) {
            return self::ordered($candidates)->take($placement->maxAds())->values();
        }

        $byId = $candidates->keyBy(fn (Ad $ad) => $ad->id());
        $weights = $byId->map(fn (Ad $ad) => $ad->weight())->all();

        return collect(WeightedPicker::pick($weights, $placement->maxAds(), $random))
            ->map(fn (string $id) => $byId->get($id))
            ->values();
    }

    /**
     * Sort ads by weight descending, then by title.
     *
     * @param  Collection<int, Ad>  $ads
     * @return Collection<int, Ad>
     */
    public static function ordered(Collection $ads): Collection
    {
        return $ads->sort(function (Ad $a, Ad $b) {
            return [$b->weight(), mb_strtolower($a->title())] <=> [$a->weight(), mb_strtolower($b->title())];
        })->values();
    }

    /**
     * Drop ads that have reached their impression or click caps.
     *
     * @param  Collection<int, Ad>  $ads
     * @return Collection<int, Ad>
     */
    private function withinCaps(Collection $ads): Collection
    {
        if (! $ads->contains(fn (Ad $ad) => $ad->hasCaps())) {
            return $ads;
        }

        $totals = $this->stats->lifetimeTotals();

        return $ads->filter(fn (Ad $ad) => $ad->withinCaps($totals[$ad->id()] ?? []));
    }
}
