<?php

namespace Billboard\Tests\Feature;

use Billboard\Ads\AdRepository;
use Billboard\Content\Shortcodes;
use Billboard\Delivery\Renderer;
use Billboard\Fieldtypes\BillboardFieldtype;
use Billboard\Listeners\EnsureFrontendAssets;
use Billboard\Listeners\InjectAdvertisementSets;
use Billboard\Placements\PlacementRepository;
use Billboard\Tests\TestCase;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Events\EntryBlueprintFound;
use Statamic\Facades\Blueprint;
use Statamic\Fields\Field;
use Statamic\Fieldtypes\Markdown;

class ContentPlacementTest extends TestCase
{
    /**
     * Create the sidebar placement and a text ad for these rendering tests.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $placements = app(PlacementRepository::class);
        $placements->save($placements->make('sidebar', ['title' => 'Sidebar']));

        $ads = app(AdRepository::class);
        $ads->save($ads->make([
            'title' => 'Launch',
            'type' => 'text',
            'headline' => 'Ship it',
            'url' => 'https://example.com/start',
            'placements' => ['sidebar'],
        ]));
    }

    #[Test]
    /**
     * Render a placement shortcode inside Markdown content.
     */
    public function markdown_content_renders_a_placement_shortcode(): void
    {
        $fieldtype = (new Markdown)->setField(new Field('content', [
            'type' => 'markdown',
            'escape_markup' => true,
        ]));

        $html = $fieldtype->augment("Hello\n\n{{ billboard:sidebar }}");

        $this->assertStringContainsString('<p>Hello</p>', $html);
        $this->assertStringContainsString('data-billboard-placement="sidebar"', $html);
        $this->assertStringContainsString('Ship it', $html);
        $this->assertStringNotContainsString('{{ billboard:sidebar }}', $html);
        $this->assertDoesNotMatchRegularExpression('/<p>\s*<aside/', $html);
    }

    #[Test]
    /**
     * Leave a shortcode as text when it is inside a fenced or inline code sample.
     */
    public function markdown_code_samples_keep_shortcodes_as_text(): void
    {
        $fieldtype = (new Markdown)->setField(new Field('content', [
            'type' => 'markdown',
            'escape_markup' => true,
        ]));

        $html = $fieldtype->augment("```\n{{ billboard:sidebar }}\n```\n\n`{{ billboard:sidebar }}`");

        $this->assertStringContainsString('{{ billboard:sidebar }}', $html);
        $this->assertStringNotContainsString('data-billboard-placement', $html);
    }

    #[Test]
    /**
     * Leave shortcodes untouched while a control panel request is rendering.
     */
    public function control_panel_requests_do_not_render_shortcodes(): void
    {
        $this->app->instance('request', Request::create('/cp/collections/pages', 'GET'));

        $html = app(Shortcodes::class)->expand('<p>{{ billboard:sidebar }}</p>');

        $this->assertStringContainsString('{{ billboard:sidebar }}', $html);
    }

    #[Test]
    /**
     * Render the placement chosen in an Advertisement field.
     */
    public function the_advertisement_field_renders_its_placement(): void
    {
        $fieldtype = (new BillboardFieldtype)->setField(new Field('advertisement', [
            'type' => 'billboard',
        ]));

        $this->assertStringContainsString('data-billboard-placement="sidebar"', $fieldtype->augment('sidebar'));
        $this->assertSame('', $fieldtype->augment(null));
        $this->assertSame('sidebar', $fieldtype->process(' sidebar '));
        $this->assertNull($fieldtype->process(''));
        $this->assertSame('Sidebar', $fieldtype->preProcessIndex('sidebar'));
    }

    #[Test]
    /**
     * Add an Advertisement set to Bard fields when an entry blueprint is resolved.
     */
    public function entry_blueprints_gain_an_advertisement_set(): void
    {
        $blueprint = Blueprint::make('page')->setContents([
            'tabs' => [
                'main' => [
                    'sections' => [[
                        'fields' => [
                            [
                                'handle' => 'content',
                                'field' => ['type' => 'bard', 'display' => 'Content'],
                            ],
                            [
                                'handle' => 'caption',
                                'field' => ['type' => 'bard', 'billboard' => false],
                            ],
                        ],
                    ]],
                ],
            ],
        ]);

        config(['billboard.content.inject_sets' => true]);

        EntryBlueprintFound::dispatch($blueprint);
        EntryBlueprintFound::dispatch($blueprint);

        $fields = collect($blueprint->contents()['tabs']['main']['sections'][0]['fields'])->keyBy('handle');
        $sets = $fields['content']['field']['sets'];

        $this->assertSame('billboard', $sets['advertisement']['fields'][0]['field']['type']);
        $this->assertSame('placement', $sets['advertisement']['fields'][0]['handle']);
        $this->assertArrayNotHasKey('sets', $fields['caption']['field']);
        $this->assertCount(1, array_filter(array_keys($sets), fn ($handle) => $handle === 'advertisement'));
    }

    #[Test]
    /**
     * Leave a blueprint alone when it has no Bard or Replicator fields.
     */
    public function blueprints_without_sets_are_left_alone(): void
    {
        $blueprint = Blueprint::make('page')->setContents([
            'tabs' => [
                'main' => [
                    'sections' => [[
                        'fields' => [[
                            'handle' => 'content',
                            'field' => ['type' => 'markdown'],
                        ]],
                    ]],
                ],
            ],
        ]);

        config(['billboard.content.inject_sets' => true]);

        app(InjectAdvertisementSets::class)->inject($blueprint);

        $field = $blueprint->contents()['tabs']['main']['sections'][0]['fields'][0]['field'];

        $this->assertSame('markdown', $field['type']);
        $this->assertArrayNotHasKey('sets', $field);
    }

    #[Test]
    /**
     * Put the frontend script on the response when an earlier render already claimed it.
     */
    public function the_response_gains_frontend_assets_when_a_placement_has_none(): void
    {
        app(Renderer::class)->placement('sidebar');

        $response = new Response('<html><body><aside data-billboard-placement="sidebar"></aside></body></html>');

        app(EnsureFrontendAssets::class)->handle(new RequestHandled(Request::create('/'), $response));

        $this->assertStringContainsString('data-billboard-endpoint', $response->getContent());
        $this->assertStringContainsString('billboard.js', $response->getContent());
    }

    #[Test]
    /**
     * Insert the frontend script once, before the document's final body tag.
     */
    public function frontend_assets_are_inserted_once_before_the_final_body(): void
    {
        app(Renderer::class)->placement('sidebar');

        $response = new Response('<html><body><script>const end = "</body>";</script><aside data-billboard-placement="sidebar"></aside></body></html>');

        app(EnsureFrontendAssets::class)->handle(new RequestHandled(Request::create('/'), $response));

        $content = $response->getContent();

        $this->assertSame(1, substr_count($content, 'data-billboard-endpoint'));
        $this->assertStringContainsString('const end = "</body>";', $content);
        $this->assertMatchesRegularExpression('/billboard\.js[\s\S]*<\/body><\/html>$/', $content);
    }

    #[Test]
    /**
     * Leave a page alone when it only mentions the placement attribute in prose.
     */
    public function a_prose_mention_of_the_placement_attribute_adds_no_script(): void
    {
        $response = new Response('<html><body><p>The attribute is data-billboard-placement.</p></body></html>');

        app(EnsureFrontendAssets::class)->handle(new RequestHandled(Request::create('/'), $response));

        $this->assertStringNotContainsString('data-billboard-endpoint', $response->getContent());
    }

    #[Test]
    /**
     * Leave Bard fields unchanged until advertisement sets are turned on.
     */
    public function advertisement_sets_stay_off_until_injection_is_enabled(): void
    {
        config(['billboard.content.inject_sets' => false]);

        $blueprint = Blueprint::make('page')->setContents([
            'tabs' => [
                'main' => [
                    'sections' => [[
                        'fields' => [
                            [
                                'handle' => 'content',
                                'field' => ['type' => 'bard', 'display' => 'Content'],
                            ],
                        ],
                    ]],
                ],
            ],
        ]);

        EntryBlueprintFound::dispatch($blueprint);

        $field = $blueprint->contents()['tabs']['main']['sections'][0]['fields'][0]['field'];

        $this->assertArrayNotHasKey('sets', $field);
    }
}
