<?php

namespace Billboard\Tests\Feature;

use Billboard\Ads\AdRepository;
use Billboard\Tests\TestCase;
use Billboard\Tracking\StatsRepository;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;

class TrackingTest extends TestCase
{
    private const BROWSER = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14.0) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Safari/605.1.15';

    private $ad;

    protected function setUp(): void
    {
        parent::setUp();

        $ads = app(AdRepository::class);
        $this->ad = $ads->save($ads->make([
            'title' => 'Tracked',
            'type' => 'text',
            'headline' => 'Hello',
            'url' => 'https://example.com/landing',
            'placements' => ['sidebar'],
        ]));
    }

    private function beacon(array $events, array $headers = [])
    {
        return $this->call('POST', '/!/billboard/impressions', [], [], [], array_merge([
            'HTTP_USER_AGENT' => self::BROWSER,
            'CONTENT_TYPE' => 'text/plain',
        ], $headers), json_encode(['e' => $events]));
    }

    private function totals(): array
    {
        $today = CarbonImmutable::now();

        return app(StatsRepository::class)->totals($today->startOfDay(), $today->endOfDay());
    }

    #[Test]
    public function it_records_impressions_without_a_csrf_token(): void
    {
        $this->beacon([[$this->ad->id(), 'sidebar'], [$this->ad->id(), 'sidebar']])->assertNoContent();

        $this->assertSame(2, $this->totals()['impressions']);
    }

    #[Test]
    public function it_ignores_unknown_ads_and_placements_the_ad_is_not_in(): void
    {
        $this->beacon([['not-an-ad', 'sidebar'], [$this->ad->id(), 'footer'], ['../../etc', 'x'], 'junk'])->assertNoContent();

        $this->assertSame(0, $this->totals()['impressions']);
    }

    #[Test]
    public function it_ignores_bots_and_privacy_signals(): void
    {
        $this->beacon([[$this->ad->id(), 'sidebar']], ['HTTP_USER_AGENT' => 'Googlebot/2.1'])->assertNoContent();
        $this->beacon([[$this->ad->id(), 'sidebar']], ['HTTP_SEC_GPC' => '1'])->assertNoContent();
        $this->beacon([[$this->ad->id(), 'sidebar']], ['HTTP_DNT' => '1'])->assertNoContent();

        $this->assertSame(0, $this->totals()['impressions']);
    }

    #[Test]
    public function it_caps_events_per_request(): void
    {
        $this->beacon(array_fill(0, 100, [$this->ad->id(), 'sidebar']))->assertNoContent();

        $this->assertSame(25, $this->totals()['impressions']);
    }

    #[Test]
    public function clicks_redirect_to_the_saved_destination_and_are_counted(): void
    {
        $this->get('/!/billboard/click/'.$this->ad->id().'/sidebar?to=https://evil.test', ['User-Agent' => self::BROWSER])
            ->assertRedirect('https://example.com/landing')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');

        $this->assertSame(1, $this->totals()['clicks']);
    }

    #[Test]
    public function clicks_on_unknown_ads_are_not_found(): void
    {
        $this->get('/!/billboard/click/missing/sidebar', ['User-Agent' => self::BROWSER])->assertNotFound();
    }

    #[Test]
    public function stats_accumulate_in_one_row_per_day(): void
    {
        $stats = app(StatsRepository::class);

        $stats->record($this->ad->id(), 'sidebar', impressions: 3);
        $stats->record($this->ad->id(), 'sidebar', impressions: 2, clicks: 1);

        $this->assertSame(['impressions' => 5, 'clicks' => 1], $this->totals());
        $this->assertSame(1, DB::table('billboard_stats')->count());
    }

    #[Test]
    public function pruning_removes_old_rows_only(): void
    {
        $stats = app(StatsRepository::class);
        $stats->record($this->ad->id(), 'sidebar', impressions: 1, date: CarbonImmutable::now()->subDays(400));
        $stats->record($this->ad->id(), 'sidebar', impressions: 1);

        $this->artisan('billboard:prune-stats', ['--days' => 30])->assertSuccessful();

        $this->assertSame(1, DB::table('billboard_stats')->count());
    }

    #[Test]
    /**
     * Ignore impressions for ads that are disabled or outside their schedule.
     */
    public function it_does_not_count_impressions_for_inactive_ads(): void
    {
        $disabled = $this->makeAd(['enabled' => false]);
        $upcoming = $this->makeAd(['start_at' => CarbonImmutable::now()->addDay()->toDateTimeString()]);
        $ended = $this->makeAd(['end_at' => CarbonImmutable::now()->subDay()->toDateTimeString()]);

        $this->beacon([
            [$disabled->id(), 'sidebar'],
            [$upcoming->id(), 'sidebar'],
            [$ended->id(), 'sidebar'],
        ])->assertNoContent();

        $this->assertSame(0, $this->totals()['impressions']);
    }

    #[Test]
    /**
     * Record an impression once when a later request runs on the same process.
     */
    public function it_does_not_record_an_impression_again_on_the_next_request(): void
    {
        $this->beacon([[$this->ad->id(), 'sidebar']])->assertNoContent();
        $this->beacon([[$this->ad->id(), 'sidebar']], ['HTTP_USER_AGENT' => 'Googlebot/2.1'])->assertNoContent();

        $this->assertSame(1, $this->totals()['impressions']);
    }

    #[Test]
    /**
     * Stop counting impressions once the ad's lifetime cap is reached.
     */
    public function it_stops_counting_impressions_at_the_cap(): void
    {
        $capped = $this->makeAd(['max_impressions' => 1]);

        $this->beacon([[$capped->id(), 'sidebar'], [$capped->id(), 'sidebar']])->assertNoContent();
        $this->beacon([[$capped->id(), 'sidebar']])->assertNoContent();

        $this->assertSame(1, $this->totals()['impressions']);
    }

    #[Test]
    /**
     * Redirect a prefetch of the click URL without recording a click.
     */
    public function it_does_not_count_prefetch_clicks(): void
    {
        $url = '/!/billboard/click/'.$this->ad->id().'/sidebar';

        $this->get($url, ['User-Agent' => self::BROWSER, 'Sec-Purpose' => 'prefetch'])
            ->assertRedirect('https://example.com/landing');
        $this->get($url, ['User-Agent' => self::BROWSER, 'Purpose' => 'prefetch'])
            ->assertRedirect('https://example.com/landing');

        $this->assertSame(0, $this->totals()['clicks']);
    }

    #[Test]
    /**
     * Redirect a click on a disabled ad without recording it.
     */
    public function it_does_not_count_clicks_for_inactive_ads(): void
    {
        $disabled = $this->makeAd(['enabled' => false]);

        $this->get('/!/billboard/click/'.$disabled->id().'/sidebar', ['User-Agent' => self::BROWSER])
            ->assertRedirect('https://example.com/landing');

        $this->assertSame(0, $this->totals()['clicks']);
    }

    #[Test]
    /**
     * Stop counting clicks once the ad's lifetime cap is reached.
     */
    public function it_stops_counting_clicks_at_the_cap(): void
    {
        $capped = $this->makeAd(['max_clicks' => 1]);
        $url = '/!/billboard/click/'.$capped->id().'/sidebar';

        $this->get($url, ['User-Agent' => self::BROWSER])->assertRedirect('https://example.com/landing');
        $this->get($url, ['User-Agent' => self::BROWSER])->assertRedirect('https://example.com/landing');

        $this->assertSame(1, $this->totals()['clicks']);
    }

    /**
     * Persist an extra ad for a tracking case.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function makeAd(array $overrides = [])
    {
        $ads = app(AdRepository::class);

        return $ads->save($ads->make([
            'title' => 'Tracked',
            'type' => 'text',
            'headline' => 'Hello',
            'url' => 'https://example.com/landing',
            'placements' => ['sidebar'],
            ...$overrides,
        ]));
    }
}
