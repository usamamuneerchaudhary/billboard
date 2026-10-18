<?php

namespace Billboard\Widgets;

use Billboard\Ads\AdRepository;
use Billboard\Http\Controllers\CP\ReportsController;
use Billboard\Tracking\StatsRepository;
use Carbon\CarbonImmutable;
use Statamic\Facades\User;
use Statamic\Widgets\VueComponent;
use Statamic\Widgets\Widget;

class BillboardPerformance extends Widget
{
    protected static $handle = 'billboard_performance';

    /**
     * Return the widget title shown in the control panel.
     */
    public static function title()
    {
        return __('Ad performance');
    }

    /**
     * Render recent impression and click totals for the busiest ads.
     */
    public function component()
    {
        if (! User::current()?->can('view billboard reports')) {
            return null;
        }

        $stats = app(StatsRepository::class);
        $days = min(365, max(1, (int) $this->config('days', 30)));
        $limit = min(20, max(1, (int) $this->config('limit', 5)));

        $to = CarbonImmutable::now()->endOfDay();
        $from = $to->subDays($days - 1)->startOfDay();

        $ads = app(AdRepository::class)->all();
        $totals = $stats->totals($from, $to);

        $rows = collect($stats->byAd($from, $to))
            ->take($limit)
            ->map(fn (array $total, string $id) => [
                'id' => $id,
                'title' => $ads->get($id)?->title() ?? __('Deleted ad'),
                'edit_url' => $ads->get($id)?->editUrl(),
                'impressions' => $total['impressions'],
                'clicks' => $total['clicks'],
                'ctr' => ReportsController::ctr($total['impressions'], $total['clicks']),
            ])
            ->values()
            ->all();

        return VueComponent::render('billboard-performance-widget', [
            'title' => $this->config('title', __('Ad performance')),
            'days' => $days,
            'available' => $stats->available(),
            'totals' => [...$totals, 'ctr' => ReportsController::ctr($totals['impressions'], $totals['clicks'])],
            'rows' => $rows,
            'reportsUrl' => cp_route('billboard.reports.index', ['range' => in_array($days, ReportsController::RANGES, true) ? $days : 30]),
        ]);
    }
}
