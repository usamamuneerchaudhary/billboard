<?php

namespace Billboard\Tracking;

use Billboard\Ads\Ad;
use Billboard\Ads\AdRepository;
use Billboard\Ads\AdStatus;
use Billboard\Placements\Placement;
use Billboard\Support\Settings;
use Illuminate\Http\Request;

class Tracker
{
    public const int MAX_EVENTS_PER_REQUEST = 25;

    /**
     * Counts accepted in the current request but not yet written.
     *
     * @var array<string, array{impressions: int, clicks: int}>
     */
    private array $pending = [];

    /**
     * Create the tracker with settings, stats, ads, and bot detection.
     */
    public function __construct(
        private Settings $settings,
        private StatsRepository $stats,
        private AdRepository $ads,
        private BotDetector $bots,
    ) {}

    /**
     * Decide whether this request should be counted.
     */
    public function shouldCount(Request $request): bool
    {
        if (! $this->settings->trackingEnabled() || $this->bots->isBot($request->userAgent())) {
            return false;
        }

        if ($this->settings->respectsPrivacySignals() && $this->hasPrivacySignal($request)) {
            return false;
        }

        return true;
    }

    /**
     * Detect a Global Privacy Control or Do Not Track header.
     */
    public function hasPrivacySignal(Request $request): bool
    {
        return $request->header('Sec-GPC') === '1' || $request->header('DNT') === '1';
    }

    /**
     * Accept viewable impressions and record them after the response is sent.
     *
     * @param  array<int, mixed>  $events  Pairs of [adId, placementHandle].
     * @return int The number of impressions accepted.
     */
    public function recordImpressions(Request $request, array $events): int
    {
        $this->pending = [];

        if (! $this->shouldCount($request)) {
            return 0;
        }

        $counts = [];

        foreach (array_slice($events, 0, self::MAX_EVENTS_PER_REQUEST) as $event) {
            if (! is_array($event) || count($event) < 2 || ! is_string($event[0]) || ! is_string($event[1])) {
                continue;
            }

            [$adId, $placement] = $event;

            if (! ($ad = $this->ads->find($adId)) || ! $this->servesPlacement($ad, $placement) || ! $this->shouldRecord($ad, 'impressions')) {
                continue;
            }

            $key = $ad->id()."\n".$placement;
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }

        if ($counts === []) {
            return 0;
        }

        $this->afterResponse(function () use ($counts) {
            foreach ($counts as $key => $count) {
                [$adId, $placement] = explode("\n", $key, 2);
                $this->stats->record($adId, $placement, impressions: $count);
            }
        });

        return array_sum($counts);
    }

    /**
     * Record a click after the response is sent when the request should count.
     */
    public function recordClick(Request $request, Ad $ad, string $placement): bool
    {
        $this->pending = [];

        if (! $this->shouldCount($request) || $this->isPrefetch($request) || ! $this->servesPlacement($ad, $placement) || ! $this->shouldRecord($ad, 'clicks')) {
            return false;
        }

        $this->afterResponse(fn () => $this->stats->record($ad->id(), $placement, clicks: 1));

        return true;
    }

    /**
     * Decide whether the browser fetched this click ahead of a visit.
     */
    public function isPrefetch(Request $request): bool
    {
        foreach (['Sec-Purpose', 'Purpose'] as $header) {
            if (str_contains(strtolower((string) $request->header($header, '')), 'prefetch')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Decide whether this ad is live and still under its caps.
     */
    private function shouldRecord(Ad $ad, string $metric): bool
    {
        if ($ad->status() !== AdStatus::Active) {
            return false;
        }

        if (! $ad->hasCaps()) {
            return true;
        }

        $pending = $this->pending[$ad->id()] ?? ['impressions' => 0, 'clicks' => 0];
        $stored = $this->stats->lifetimeTotals()[$ad->id()] ?? [];
        $totals = [
            'impressions' => ($stored['impressions'] ?? 0) + $pending['impressions'],
            'clicks' => ($stored['clicks'] ?? 0) + $pending['clicks'],
        ];

        if (! $ad->withinCaps($totals)) {
            return false;
        }

        $pending[$metric]++;
        $this->pending[$ad->id()] = $pending;

        return true;
    }

    /**
     * Run a stats write after the response, and only on the first terminate.
     *
     * Laravel keeps terminating callbacks for the life of the process, so a
     * later request would otherwise record the same event again.
     */
    private function afterResponse(callable $callback): void
    {
        $pending = true;

        app()->terminating(function () use (&$pending, $callback) {
            if (! $pending) {
                return;
            }

            $pending = false;
            $callback();
        });
    }

    /**
     * Decide whether the ad is assigned to the placement being tracked.
     */
    public function servesPlacement(Ad $ad, string $placement): bool
    {
        return $placement === Placement::DIRECT || in_array($placement, $ad->placements(), true);
    }
}
