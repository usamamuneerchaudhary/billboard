<?php

namespace Billboard\Console\Commands;

use Billboard\Support\Settings;
use Billboard\Tracking\StatsRepository;
use Illuminate\Console\Command;
use Statamic\Console\RunsInPlease;

class PruneStats extends Command
{
    use RunsInPlease;

    protected $signature = 'billboard:prune-stats
        {--days= : Delete daily totals older than this many days. Defaults to the retention setting.}';

    protected $description = 'Delete Billboard statistics older than the retention period';

    /**
     * Delete daily stat rows older than the given or configured retention.
     */
    public function handle(StatsRepository $stats, Settings $settings): int
    {
        if (! $stats->available()) {
            $this->components->warn('The Billboard stats table does not exist yet. Run `php artisan migrate` first.');

            return self::SUCCESS;
        }

        $days = $this->option('days') !== null ? (int) $this->option('days') : $settings->retentionDays();

        if ($days <= 0) {
            $this->components->info('Retention is set to keep everything. Nothing was deleted.');

            return self::SUCCESS;
        }

        $deleted = $stats->prune($days);

        $this->components->info("Deleted {$deleted} daily rows older than {$days} days.");

        return self::SUCCESS;
    }
}
