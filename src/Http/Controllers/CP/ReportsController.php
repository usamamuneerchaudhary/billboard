<?php

namespace Billboard\Http\Controllers\CP;

use Billboard\Ads\Ad;
use Billboard\Ads\AdRepository;
use Billboard\Placements\Placement;
use Billboard\Placements\PlacementRepository;
use Billboard\Support\Settings;
use Billboard\Tracking\StatsRepository;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Statamic\Http\Controllers\CP\CpController;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportsController extends CpController
{
    public const array RANGES = [7, 30, 90, 365];

    /**
     * Render the reports page for the selected date range and filters.
     */
    public function index(Request $request, StatsRepository $stats, AdRepository $ads, PlacementRepository $placements, Settings $settings)
    {
        $this->authorize('view billboard reports', [], __('You are not authorized to view ad reports.'));

        [$days, $from, $to, $placement, $adId] = $this->filters($request, $ads, $placements);

        $previousTo = $from->subDay()->endOfDay();
        $previousFrom = $previousTo->subDays($days - 1)->startOfDay();

        $allAds = $ads->all();
        $totals = $stats->totals($from, $to, $placement, $adId);
        $previous = $stats->totals($previousFrom, $previousTo, $placement, $adId);

        $rows = collect($stats->byAd($from, $to, $placement, $adId))
            ->map(function (array $total, string $id) use ($allAds) {
                /** @var Ad|null $ad */
                $ad = $allAds->get($id);

                return [
                    'id' => $id,
                    'title' => $ad?->title() ?? __('Deleted ad'),
                    'deleted' => $ad === null,
                    'edit_url' => $ad?->editUrl(),
                    'impressions' => $total['impressions'],
                    'clicks' => $total['clicks'],
                    'ctr' => self::ctr($total['impressions'], $total['clicks']),
                ];
            })
            ->values();

        return Inertia::render('billboard::ReportsIndex', [
            'available' => $stats->available(),
            'trackingEnabled' => $settings->trackingEnabled(),
            'filters' => [
                'range' => $days,
                'placement' => $placement,
                'ad' => $adId,
            ],
            'ranges' => collect(self::RANGES)->map(fn (int $range) => [
                'value' => $range,
                'label' => __('Last :count days', ['count' => $range]),
            ])->all(),
            'placementOptions' => $placements->all()->map(fn (Placement $p) => [
                'value' => $p->handle(),
                'label' => $p->title(),
            ])->values()->all(),
            'adOptions' => $allAds->map(fn (Ad $ad) => [
                'value' => $ad->id(),
                'label' => $ad->title(),
            ])->values()->all(),
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'totals' => [...$totals, 'ctr' => self::ctr($totals['impressions'], $totals['clicks'])],
            'previous' => [...$previous, 'ctr' => self::ctr($previous['impressions'], $previous['clicks'])],
            'series' => $stats->byDay($from, $to, $placement, $adId),
            'rows' => $rows->all(),
            'indexUrl' => cp_route('billboard.reports.index'),
            'exportUrl' => cp_route('billboard.reports.export', array_filter([
                'range' => $days,
                'placement' => $placement,
                'ad' => $adId,
            ])),
            'settingsUrl' => cp_route('addons.settings.edit', 'billboard'),
            'migrationCommand' => 'php artisan migrate',
        ]);
    }

    /**
     * Stream a CSV export of daily totals for the selected filters.
     */
    public function export(Request $request, StatsRepository $stats, AdRepository $ads, PlacementRepository $placements): StreamedResponse
    {
        $this->authorize('view billboard reports', [], __('You are not authorized to view ad reports.'));

        [$days, $from, $to, $placement, $adId] = $this->filters($request, $ads, $placements);

        $titles = $ads->all()->map->title();
        $filename = sprintf('billboard-%s-to-%s.csv', $from->toDateString(), $to->toDateString());

        return response()->streamDownload(function () use ($stats, $from, $to, $placement, $adId, $titles) {
            $out = fopen('php://output', 'w');

            fputcsv($out, ['date', 'ad_id', 'ad_title', 'placement', 'impressions', 'clicks', 'ctr_percent'], escape: '');

            foreach ($stats->rows($from, $to, $placement, $adId) as $row) {
                fputcsv($out, [
                    substr((string) $row->date, 0, 10),
                    $row->ad_id,
                    self::csvSafe($titles->get($row->ad_id, __('Deleted ad'))),
                    self::csvSafe($row->placement),
                    (int) $row->impressions,
                    (int) $row->clicks,
                    self::ctr((int) $row->impressions, (int) $row->clicks),
                ], escape: '');
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store',
        ]);
    }

    /**
     * Return the click-through rate as a percentage.
     */
    public static function ctr(int $impressions, int $clicks): float
    {
        return $impressions > 0 ? round($clicks / $impressions * 100, 2) : 0.0;
    }

    /**
     * Prevent spreadsheet apps from treating a cell as a formula.
     */
    public static function csvSafe(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }

    /**
     * Resolve the report date range and optional placement and ad filters.
     *
     * @return array{0: int, 1: CarbonImmutable, 2: CarbonImmutable, 3: ?string, 4: ?string}
     */
    private function filters(Request $request, AdRepository $ads, PlacementRepository $placements): array
    {
        $days = (int) $request->query('range', 30);
        $days = in_array($days, self::RANGES, true) ? $days : 30;

        $to = CarbonImmutable::now()->endOfDay();
        $from = $to->subDays($days - 1)->startOfDay();

        $placement = $request->query('placement');
        $placement = is_string($placement) && $placements->find($placement) ? $placement : null;

        $adId = $request->query('ad');
        $adId = is_string($adId) && preg_match('/^[A-Za-z0-9_-]{1,64}$/', $adId) ? $adId : null;

        return [$days, $from, $to, $placement, $adId];
    }
}
