<?php

namespace Billboard\Tests\Feature;

use Billboard\Ads\AdRepository;
use Billboard\Delivery\AdSelector;
use Billboard\Delivery\DeliveryContext;
use Billboard\Placements\PlacementRepository;
use Billboard\Tests\TestCase;
use Billboard\Tracking\StatsRepository;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Test;

class AdSelectionTest extends TestCase
{
    private function context(string $path = '/', ?string $site = 'default', ?CarbonImmutable $now = null): DeliveryContext
    {
        return new DeliveryContext($now ?? CarbonImmutable::parse('2026-09-16 12:00'), $site, $path);
    }

    private function textAd(array $data = [])
    {
        $ads = app(AdRepository::class);

        return $ads->save($ads->make([
            'title' => 'Ad',
            'type' => 'text',
            'headline' => 'Try it',
            'url' => 'https://example.com',
            'placements' => ['sidebar'],
            ...$data,
        ]));
    }

    private function placement(array $data = [])
    {
        $placements = app(PlacementRepository::class);

        return $placements->save($placements->make('sidebar', ['title' => 'Sidebar', ...$data]));
    }

    #[Test]
    public function it_only_offers_enabled_ads_in_the_placement(): void
    {
        $placement = $this->placement();
        $shown = $this->textAd(['title' => 'Shown']);
        $this->textAd(['title' => 'Disabled', 'enabled' => false]);
        $this->textAd(['title' => 'Elsewhere', 'placements' => ['footer']]);

        $ids = app(AdSelector::class)->candidates($placement, $this->context())->map->id()->all();

        $this->assertSame([$shown->id()], $ids);
    }

    #[Test]
    public function it_respects_schedules_on_the_server(): void
    {
        $placement = $this->placement();
        $running = $this->textAd(['title' => 'Running', 'start_at' => '2026-09-01 00:00', 'end_at' => '2026-10-01 00:00']);
        $this->textAd(['title' => 'Upcoming', 'start_at' => '2026-09-20 00:00']);
        $this->textAd(['title' => 'Ended', 'end_at' => '2026-09-10 00:00']);

        $ids = app(AdSelector::class)->candidates($placement, $this->context())->map->id()->all();

        $this->assertSame([$running->id()], $ids);
    }

    #[Test]
    public function browser_placements_include_upcoming_ads_so_cached_pages_start_them_on_time(): void
    {
        $placement = $this->placement(['delivery' => 'client']);
        $this->textAd(['title' => 'Upcoming', 'start_at' => '2026-09-20 00:00']);
        $this->textAd(['title' => 'Ended', 'end_at' => '2026-09-10 00:00']);

        $titles = app(AdSelector::class)->candidates($placement, $this->context())->map->title()->all();

        $this->assertSame(['Upcoming'], $titles);
    }

    #[Test]
    public function it_applies_path_and_site_targeting(): void
    {
        $placement = $this->placement();
        $this->textAd(['title' => 'Blog only', 'include_paths' => ['/blog/*']]);
        $this->textAd(['title' => 'Not on pricing', 'exclude_paths' => ['/pricing']]);
        $this->textAd(['title' => 'French site', 'sites' => ['french']]);

        $selector = app(AdSelector::class);

        $this->assertEqualsCanonicalizing(
            ['Blog only', 'Not on pricing'],
            $selector->candidates($placement, $this->context('/blog/hello'))->map->title()->all()
        );

        $this->assertSame([], $selector->candidates($placement, $this->context('/pricing'))->map->title()->all());
    }

    #[Test]
    public function it_stops_showing_ads_that_reached_their_caps(): void
    {
        $placement = $this->placement();
        $capped = $this->textAd(['title' => 'Capped', 'max_clicks' => 2]);
        $this->textAd(['title' => 'Uncapped']);

        app(StatsRepository::class)->record($capped->id(), 'sidebar', impressions: 50, clicks: 2);

        $titles = app(AdSelector::class)->candidates($placement, $this->context())->map->title()->all();

        $this->assertSame(['Uncapped'], $titles);
    }

    #[Test]
    public function ordered_rotation_shows_the_highest_weights_first(): void
    {
        $placement = $this->placement(['rotation' => 'ordered', 'max_ads' => 2]);
        $this->textAd(['title' => 'Low', 'weight' => 1]);
        $this->textAd(['title' => 'High', 'weight' => 90]);
        $this->textAd(['title' => 'Middle', 'weight' => 40]);

        $selector = app(AdSelector::class);
        $chosen = $selector->choose($placement, $selector->candidates($placement, $this->context()));

        $this->assertSame(['High', 'Middle'], $chosen->map->title()->all());
    }

    #[Test]
    public function it_ignores_ads_that_cannot_render(): void
    {
        $placement = $this->placement();
        $this->textAd(['title' => 'No link', 'url' => 'javascript:alert(1)']);
        $this->textAd(['title' => 'No headline', 'headline' => '']);

        $this->assertCount(0, app(AdSelector::class)->candidates($placement, $this->context()));
    }
}
