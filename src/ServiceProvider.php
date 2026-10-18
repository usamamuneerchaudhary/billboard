<?php

namespace Billboard;

use Billboard\Ads\AdRepository;
use Billboard\Content\ShortcodeExtension;
use Billboard\Delivery\AdSelector;
use Billboard\Delivery\Renderer;
use Billboard\Placements\PlacementRepository;
use Billboard\Stores\YamlDirectory;
use Billboard\Support\Settings;
use Billboard\Tracking\BotDetector;
use Billboard\Tracking\StatsRepository;
use Billboard\Tracking\Tracker;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\RateLimiter;
use Statamic\Facades\CP\Nav;
use Statamic\Facades\Markdown;
use Statamic\Facades\Permission;
use Statamic\Providers\AddonServiceProvider;
use Throwable;

class ServiceProvider extends AddonServiceProvider
{
    protected $vite = [
        'input' => [
            'resources/js/addon.js',
            'resources/css/addon.css',
        ],
        'publicDirectory' => 'resources/dist',
    ];

    protected $publishables = [
        __DIR__.'/../resources/dist/frontend' => 'frontend',
    ];

    /**
     * Bind Billboard's repositories, selector, renderer, and tracker.
     */
    public function register(): void
    {
        $this->app->singleton(Settings::class);

        $this->app->singleton(AdRepository::class, fn ($app) => new AdRepository(
            new YamlDirectory($app['files'], $app['cache.store'], (string) config('billboard.storage.ads', base_path('content/billboard/ads')))
        ));

        $this->app->singleton(PlacementRepository::class, fn ($app) => new PlacementRepository(
            new YamlDirectory($app['files'], $app['cache.store'], (string) config('billboard.storage.placements', base_path('content/billboard/placements'))),
            $app->make(AdRepository::class),
        ));

        $this->app->singleton(StatsRepository::class, fn ($app) => new StatsRepository($app['db']));

        $this->app->singleton(BotDetector::class, fn () => new BotDetector(
            array_values(array_filter((array) config('billboard.tracking.bot_patterns', []), 'is_string'))
        ));

        $this->app->singleton(AdSelector::class);
        $this->app->singleton(Renderer::class);
        $this->app->singleton(Tracker::class);
    }

    /**
     * Load migrations and register permissions, navigation, tracking limits, and Blade directives.
     */
    public function bootAddon(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'billboard-migrations');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/billboard'),
        ], 'billboard-views');

        $this->registerPermissions();
        $this->registerNavigation();
        $this->registerRateLimiter();
        $this->registerBladeDirectives();
        $this->registerContentShortcodes();
    }

    /**
     * Prune expired daily totals once a day, on one server, without overlapping runs.
     */
    protected function schedule(Schedule $schedule)
    {
        try {
            $days = $this->app->make(Settings::class)->retentionDays();
        } catch (Throwable) {
            return;
        }

        if ($days > 0) {
            $schedule->command('billboard:prune-stats')
                ->dailyAt('03:17')
                ->withoutOverlapping()
                ->onOneServer();
        }
    }

    /**
     * Register the Advertising permission group for ads, placements, and reports.
     */
    private function registerPermissions(): void
    {
        Permission::extend(function () {
            Permission::group('billboard', __('Advertising'), function () {
                Permission::register('view billboard ads', function ($permission) {
                    $permission->label(__('View ads'))->children([
                        Permission::make('edit billboard ads')->label(__('Edit ads'))->children([
                            Permission::make('create billboard ads')->label(__('Create ads')),
                            Permission::make('delete billboard ads')->label(__('Delete ads')),
                            Permission::make('edit billboard html ads')
                                ->label(__('Edit HTML ads'))
                                ->description(__('HTML ads can run any script on your site. Only grant this to people you trust with site code.')),
                        ]),
                    ]);
                });

                Permission::register('manage billboard placements', function ($permission) {
                    $permission->label(__('Manage placements'))
                        ->description(__('Includes editing fallback HTML, which is output on your site as-is.'));
                });

                Permission::register('view billboard reports', function ($permission) {
                    $permission->label(__('View ad reports'));
                });
            });
        });
    }

    /**
     * Add Ads, Placements, and Reports to the control panel navigation.
     */
    private function registerNavigation(): void
    {
        Nav::extend(function ($nav) {
            $nav->create(__('Ads'))
                ->section(__('Advertising'))
                ->route('billboard.ads.index')
                ->icon('megaphone')
                ->can('view billboard ads');

            $nav->create(__('Placements'))
                ->section(__('Advertising'))
                ->route('billboard.placements.index')
                ->icon('layout-sidebar-right')
                ->can('manage billboard placements');

            $nav->create(__('Reports'))
                ->section(__('Advertising'))
                ->route('billboard.reports.index')
                ->icon('money-graph-bar-increase')
                ->can('view billboard reports');
        });
    }

    /**
     * Limit impression and click requests per visitor IP.
     */
    private function registerRateLimiter(): void
    {
        RateLimiter::for('billboard.tracking', function (Request $request) {
            return Limit::perMinute(max(1, (int) config('billboard.tracking.rate_limit', 120)))
                ->by('billboard|'.$request->ip());
        });
    }

    /**
     * Expand {{ billboard:handle }} shortcodes inside Markdown content.
     */
    private function registerContentShortcodes(): void
    {
        $this->app->booted(function () {
            Markdown::addExtension(fn () => new ShortcodeExtension);
        });
    }

    /**
     * Register the @billboard and @billboardAssets Blade directives.
     */
    private function registerBladeDirectives(): void
    {
        Blade::directive('billboard', fn (string $expression) => "<?php echo app(\\Billboard\\Delivery\\Renderer::class)->placement({$expression}); ?>");

        Blade::directive('billboardAssets', fn () => '<?php echo app(\Billboard\Delivery\Renderer::class)->assets(); ?>');
    }
}
