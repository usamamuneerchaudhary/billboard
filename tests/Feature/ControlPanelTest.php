<?php

namespace Billboard\Tests\Feature;

use Billboard\Ads\AdRepository;
use Billboard\Placements\PlacementRepository;
use Billboard\Tests\TestCase;
use Billboard\Tracking\StatsRepository;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;

class ControlPanelTest extends TestCase
{
    #[Test]
    public function guests_are_sent_to_the_login_page(): void
    {
        $this->get(cp_route('billboard.ads.index'))->assertRedirect(cp_route('login'));
    }

    #[Test]
    public function users_without_permission_cannot_view_ads(): void
    {
        $this->actingAs($this->userWithPermissions([]))
            ->get(cp_route('billboard.ads.index'))
            ->assertRedirect();
    }

    #[Test]
    public function users_with_permission_see_the_ads_listing(): void
    {
        $this->actingAs($this->userWithPermissions(['view billboard ads']))
            ->get(cp_route('billboard.ads.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('billboard::AdsIndex')->has('ads', 0));
    }

    #[Test]
    public function creating_an_ad_requires_the_create_permission(): void
    {
        $this->actingAs($this->userWithPermissions(['view billboard ads', 'edit billboard ads']))
            ->postJson(cp_route('billboard.ads.store'), ['title' => 'Nope'])
            ->assertForbidden();
    }

    #[Test]
    public function an_editor_can_create_a_text_ad(): void
    {
        $response = $this->actingAs($this->superUser())
            ->postJson(cp_route('billboard.ads.store'), [
                'title' => 'Spring sale',
                'type' => 'text',
                'headline' => 'Save 20%',
                'url' => 'https://example.com/sale',
                'enabled' => true,
                'weight' => 10,
                'devices' => ['desktop', 'mobile'],
            ])
            ->assertOk()
            ->assertJsonStructure(['redirect']);

        $ad = app(AdRepository::class)->all()->first();

        $this->assertSame('Spring sale', $ad->title());
        $this->assertSame($ad->editUrl(), $response->json('redirect'));
    }

    #[Test]
    public function unsafe_destination_urls_are_rejected(): void
    {
        $this->actingAs($this->superUser())
            ->postJson(cp_route('billboard.ads.store'), [
                'title' => 'Bad link',
                'type' => 'text',
                'headline' => 'Click',
                'url' => 'javascript:alert(document.cookie)',
                'weight' => 10,
                'devices' => ['desktop'],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('url');
    }

    #[Test]
    public function html_ads_require_the_html_permission(): void
    {
        $user = $this->userWithPermissions(['view billboard ads', 'edit billboard ads', 'create billboard ads']);

        $this->actingAs($user)
            ->postJson(cp_route('billboard.ads.store'), [
                'title' => 'Script',
                'type' => 'html',
                'html' => '<script>alert(1)</script>',
                'weight' => 10,
                'devices' => ['desktop'],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('type');

        $this->assertCount(0, app(AdRepository::class)->all());
    }

    #[Test]
    public function placement_handles_must_be_unique(): void
    {
        $placements = app(PlacementRepository::class);
        $placements->save($placements->make('sidebar', ['title' => 'Sidebar']));

        $this->actingAs($this->superUser())
            ->postJson(cp_route('billboard.placements.store'), [
                'title' => 'Sidebar again',
                'handle' => 'sidebar',
                'delivery' => 'server',
                'rotation' => 'weighted',
                'max_ads' => 1,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('handle');
    }

    #[Test]
    public function deleting_a_placement_removes_it_from_ads(): void
    {
        $placements = app(PlacementRepository::class);
        $ads = app(AdRepository::class);
        $placement = $placements->save($placements->make('sidebar', ['title' => 'Sidebar']));
        $ad = $ads->save($ads->make(['title' => 'Ad', 'placements' => ['sidebar', 'footer']]));

        $placements->delete($placement);

        $this->assertSame(['footer'], $ads->find($ad->id())->placements());
    }

    #[Test]
    public function reports_show_a_setup_notice_when_the_table_is_missing(): void
    {
        Schema::drop('billboard_stats');
        app()->forgetInstance(StatsRepository::class);

        $this->actingAs($this->userWithPermissions(['view billboard reports']))
            ->get(cp_route('billboard.reports.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('billboard::ReportsIndex')->where('available', false));
    }

    #[Test]
    public function the_store_refuses_keys_that_could_escape_its_directory(): void
    {
        $this->assertNull(app(AdRepository::class)->find('../../config/app'));
        $this->assertNull(app(PlacementRepository::class)->find('..'));
    }
}
