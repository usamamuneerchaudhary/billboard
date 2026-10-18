<?php

namespace Billboard\Tests\Feature;

use Billboard\Ads\AdRepository;
use Billboard\Delivery\Renderer;
use Billboard\Placements\PlacementRepository;
use Billboard\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Facades\Antlers;
use Statamic\Facades\Blink;

class RenderingTest extends TestCase
{
    /**
     * Create the sidebar placement used by these rendering tests.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $placements = app(PlacementRepository::class);
        $placements->save($placements->make('sidebar', ['title' => 'Sidebar', 'width' => 300, 'height' => 250]));
    }

    /**
     * Save a text ad assigned to the sidebar placement.
     */
    private function ad(array $data = [])
    {
        $ads = app(AdRepository::class);

        return $ads->save($ads->make([
            'title' => 'Launch',
            'type' => 'text',
            'headline' => 'Ship <faster>',
            'body' => 'Deploy in minutes',
            'button_text' => 'Start',
            'url' => 'https://example.com/start',
            'new_tab' => true,
            'placements' => ['sidebar'],
            ...$data,
        ]));
    }

    #[Test]
    /**
     * Render a labelled, sponsored image or text placement.
     */
    public function it_renders_a_labelled_sponsored_placement(): void
    {
        $ad = $this->ad();

        $html = app(Renderer::class)->placement('sidebar');

        $this->assertStringContainsString('data-billboard-placement="sidebar"', $html);
        $this->assertStringContainsString('aria-label="Advertisement"', $html);
        $this->assertStringContainsString('class="billboard__label">Advertisement<', $html);
        $this->assertStringContainsString('--billboard-width: 300px', $html);
        $this->assertStringContainsString('rel="sponsored noopener"', $html);
        $this->assertStringContainsString('target="_blank"', $html);
        $this->assertStringContainsString('/!/billboard/click/'.$ad->id().'/sidebar', $html);
        $this->assertStringContainsString('Ship &lt;faster&gt;', $html, 'Text ads are escaped');
        $this->assertStringContainsString('billboard.js', $html);
    }

    #[Test]
    /**
     * Output the frontend script only with the first placement on a page.
     */
    public function it_outputs_the_script_once_per_request(): void
    {
        $this->ad();
        $renderer = app(Renderer::class);

        $first = $renderer->placement('sidebar');
        $second = $renderer->placement('sidebar');

        $this->assertStringContainsString('billboard.js', $first);
        $this->assertStringNotContainsString('billboard.js', $second);
    }

    #[Test]
    /**
     * Render nothing when the placement is missing or has no ads.
     */
    public function it_renders_nothing_for_unknown_or_empty_placements(): void
    {
        $this->assertSame('', app(Renderer::class)->placement('missing'));
        $this->assertSame('', app(Renderer::class)->placement('sidebar'));
    }

    #[Test]
    /**
     * Render the placement fallback when no ads are eligible.
     */
    public function it_renders_the_fallback_when_no_ads_are_available(): void
    {
        $placements = app(PlacementRepository::class);
        $placements->save($placements->find('sidebar')->merge(['fallback' => '<p class="house">Advertise here</p>']));

        $this->assertStringContainsString('<p class="house">Advertise here</p>', app(Renderer::class)->placement('sidebar'));
    }

    #[Test]
    /**
     * Embed every eligible ad in a template when the placement chooses ads in the browser.
     */
    public function browser_placements_embed_candidates_as_templates(): void
    {
        $placements = app(PlacementRepository::class);
        $placements->save($placements->find('sidebar')->merge(['delivery' => 'client']));
        $this->ad(['title' => 'One']);
        $this->ad(['title' => 'Two']);

        $html = app(Renderer::class)->placement('sidebar');

        $this->assertSame(2, substr_count($html, '<template data-billboard-candidate>'));
        $this->assertStringContainsString('data-billboard-mode="client"', $html);
    }

    #[Test]
    /**
     * Hide the visible label while keeping an accessible name.
     */
    public function the_label_can_be_hidden_per_placement(): void
    {
        $placements = app(PlacementRepository::class);
        $placements->save($placements->find('sidebar')->merge(['label' => '-']));
        $this->ad();

        $html = app(Renderer::class)->placement('sidebar');

        $this->assertStringNotContainsString('billboard__label', $html);
        $this->assertStringContainsString('aria-label="Advertisement"', $html, 'Screen readers still hear it');
    }

    #[Test]
    /**
     * Replace the click-url macro in HTML ads with the tracked redirect.
     */
    public function html_ads_get_a_tracked_click_url_macro(): void
    {
        $ad = $this->ad(['type' => 'html', 'html' => '<a href="%%CLICK_URL%%">Go</a>']);

        $html = app(Renderer::class)->placement('sidebar');

        $this->assertStringContainsString('<a href="/!/billboard/click/'.$ad->id().'/sidebar">Go</a>', $html);
    }

    #[Test]
    /**
     * Render placements from a trusted Antlers tag, including a dictionary handle.
     */
    public function the_antlers_tag_renders_placements_and_dictionary_values(): void
    {
        $this->ad();

        $this->assertStringContainsString('data-billboard-placement="sidebar"', (string) Antlers::parse('{{ billboard:sidebar }}', [], true));

        Blink::flush();

        $this->assertStringContainsString(
            'data-billboard-placement="sidebar"',
            (string) Antlers::parse('{{ billboard:placement :handle="slot" }}', ['slot' => 'sidebar'], true)
        );
    }

    #[Test]
    /**
     * Expose the chosen ads to a trusted Antlers tag pair.
     */
    public function the_tag_pair_exposes_ads_for_custom_markup(): void
    {
        $this->ad(['title' => 'Custom']);

        $output = (string) Antlers::parse(
            '{{ billboard:placement handle="sidebar" }}<div {{ placement_attributes }}>{{ ads }}<a href="{{ url }}" {{ attributes }}>{{ headline }}</a>{{ /ads }}</div>{{ /billboard:placement }}',
            [],
            true
        );

        $this->assertStringContainsString('data-billboard-placement="sidebar"', $output);
        $this->assertStringContainsString('data-billboard-ad="', $output);
        $this->assertStringContainsString('/!/billboard/click/', $output);
    }
}
