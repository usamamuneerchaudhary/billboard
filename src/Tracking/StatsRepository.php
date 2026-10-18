<?php

namespace Billboard\Tracking;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class StatsRepository
{
    private const string LIFETIME_TOTALS_KEY = 'billboard:lifetime-totals';

    private ?bool $available = null;

    /**
     * Create the repository with the database manager.
     */
    public function __construct(private DatabaseManager $db) {}

    /**
     * Whether the stats table exists. Checked once per process so pages keep
     * working (without reporting) if the migration hasn't been run yet.
     */
    public function available(): bool
    {
        if ($this->available !== null) {
            return $this->available;
        }

        try {
            return $this->available = $this->connection()->getSchemaBuilder()->hasTable($this->table());
        } catch (Throwable) {
            return $this->available = false;
        }
    }

    /**
     * Record impression and click counts for an ad and placement on a date.
     */
    public function record(string $adId, string $placement, int $impressions = 0, int $clicks = 0, ?CarbonInterface $date = null): void
    {
        if (($impressions <= 0 && $clicks <= 0) || ! $this->available()) {
            return;
        }

        $date = ($date ?? CarbonImmutable::now())->toDateString();
        $keys = ['ad_id' => $adId, 'placement' => $placement, 'date' => $date];

        try {
            if ($this->increment($keys, $impressions, $clicks) > 0) {
                $this->forgetLifetimeTotals();

                return;
            }

            try {
                $this->query()->insert([...$keys, 'impressions' => max(0, $impressions), 'clicks' => max(0, $clicks)]);
            } catch (UniqueConstraintViolationException) {
                // Another request inserted the row first.
                $this->increment($keys, $impressions, $clicks);
            }

            $this->forgetLifetimeTotals();
        } catch (Throwable $e) {
            Log::warning('Billboard could not record ad statistics: '.$e->getMessage());
        }
    }

    /**
     * Return summed impressions and clicks for the given filters.
     *
     * @return array{impressions: int, clicks: int}
     */
    public function totals(CarbonInterface $from, CarbonInterface $to, ?string $placement = null, ?string $adId = null): array
    {
        if (! $this->available()) {
            return ['impressions' => 0, 'clicks' => 0];
        }

        $row = $this->filtered($from, $to, $placement, $adId)
            ->selectRaw('COALESCE(SUM(impressions), 0) as impressions, COALESCE(SUM(clicks), 0) as clicks')
            ->first();

        return ['impressions' => (int) ($row->impressions ?? 0), 'clicks' => (int) ($row->clicks ?? 0)];
    }

    /**
     * Return impression and click totals grouped by ad.
     *
     * @return array<string, array{impressions: int, clicks: int}> Keyed by ad ID, busiest first.
     */
    public function byAd(CarbonInterface $from, CarbonInterface $to, ?string $placement = null, ?string $adId = null): array
    {
        if (! $this->available()) {
            return [];
        }

        return $this->filtered($from, $to, $placement, $adId)
            ->select('ad_id')
            ->selectRaw('SUM(impressions) as impressions, SUM(clicks) as clicks')
            ->groupBy('ad_id')
            ->orderByDesc('impressions')
            ->get()
            ->mapWithKeys(fn ($row) => [(string) $row->ad_id => [
                'impressions' => (int) $row->impressions,
                'clicks' => (int) $row->clicks,
            ]])
            ->all();
    }

    /**
     * One entry per calendar day in the range, including days with no data.
     *
     * @return array<int, array{date: string, impressions: int, clicks: int}>
     */
    public function byDay(CarbonInterface $from, CarbonInterface $to, ?string $placement = null, ?string $adId = null): array
    {
        $rows = [];

        if ($this->available()) {
            $rows = $this->filtered($from, $to, $placement, $adId)
                ->select('date')
                ->selectRaw('SUM(impressions) as impressions, SUM(clicks) as clicks')
                ->groupBy('date')
                ->get()
                ->mapWithKeys(fn ($row) => [substr((string) $row->date, 0, 10) => [
                    'impressions' => (int) $row->impressions,
                    'clicks' => (int) $row->clicks,
                ]])
                ->all();
        }

        $series = [];

        foreach (CarbonPeriod::create($from->toDateString(), $to->toDateString()) as $day) {
            $key = $day->toDateString();
            $series[] = ['date' => $key, ...($rows[$key] ?? ['impressions' => 0, 'clicks' => 0])];
        }

        return $series;
    }

    /**
     * Every row in the range, for CSV export.
     */
    public function rows(CarbonInterface $from, CarbonInterface $to, ?string $placement = null, ?string $adId = null): iterable
    {
        if (! $this->available()) {
            return [];
        }

        return $this->filtered($from, $to, $placement, $adId)
            ->orderBy('date')
            ->orderBy('ad_id')
            ->orderBy('placement')
            ->cursor();
    }

    /**
     * Drop the cached lifetime totals so the next cap check sees this write.
     */
    private function forgetLifetimeTotals(): void
    {
        Cache::forget(self::LIFETIME_TOTALS_KEY);
    }

    /**
     * Lifetime totals per ad, used to enforce impression and click caps.
     *
     * @return array<string, array{impressions: int, clicks: int}>
     */
    public function lifetimeTotals(): array
    {
        if (! $this->available()) {
            return [];
        }

        $seconds = max(1, (int) config('billboard.tracking.cap_cache_seconds', 60));

        return Cache::remember(self::LIFETIME_TOTALS_KEY, $seconds, function () {
            return $this->query()
                ->select('ad_id')
                ->selectRaw('SUM(impressions) as impressions, SUM(clicks) as clicks')
                ->groupBy('ad_id')
                ->get()
                ->mapWithKeys(fn ($row) => [(string) $row->ad_id => [
                    'impressions' => (int) $row->impressions,
                    'clicks' => (int) $row->clicks,
                ]])
                ->all();
        });
    }

    /**
     * Delete daily totals older than the given number of days.
     */
    public function prune(int $days): int
    {
        if ($days <= 0 || ! $this->available()) {
            return 0;
        }

        return $this->query()
            ->where('date', '<', CarbonImmutable::now()->subDays($days)->toDateString())
            ->delete();
    }

    /**
     * Return the configured stats table name.
     */
    public function table(): string
    {
        return (string) config('billboard.tracking.table', 'billboard_stats');
    }

    /**
     * Add impression and click counts to an existing daily row.
     */
    private function increment(array $keys, int $impressions, int $clicks): int
    {
        return $this->query()->where($keys)->update([
            'impressions' => $this->connection()->raw('impressions + '.max(0, $impressions)),
            'clicks' => $this->connection()->raw('clicks + '.max(0, $clicks)),
        ]);
    }

    /**
     * Build a query limited to the date range and optional filters.
     */
    private function filtered(CarbonInterface $from, CarbonInterface $to, ?string $placement, ?string $adId): Builder
    {
        return $this->query()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->when($placement, fn (Builder $query) => $query->where('placement', $placement))
            ->when($adId, fn (Builder $query) => $query->where('ad_id', $adId));
    }

    /**
     * Return a query builder for the stats table.
     */
    private function query(): Builder
    {
        return $this->connection()->table($this->table());
    }

    /**
     * Return the database connection used for stats.
     */
    private function connection(): Connection
    {
        return $this->db->connection(config('billboard.tracking.connection'));
    }
}
