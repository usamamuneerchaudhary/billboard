<?php

namespace Billboard\Tags;

use Billboard\Ads\AdRepository;
use Billboard\Delivery\AdSelector;
use Billboard\Delivery\DeliveryContext;
use Billboard\Delivery\Renderer;
use Billboard\Placements\Placement;
use Statamic\Fields\LabeledValue;
use Statamic\Fields\Value;
use Statamic\Tags\Tags;

class Billboard extends Tags
{
    protected static $handle = 'billboard';

    /**
     * Render the placement named by the tag method.
     */
    public function wildcard($method)
    {
        return $this->renderPlacement($method);
    }

    /**
     * Render the placement given by the handle parameter.
     */
    public function placement()
    {
        return $this->renderPlacement($this->params->get(['handle', 'placement', 'from']));
    }

    /**
     * Render a single ad by id.
     */
    public function ad()
    {
        $renderer = app(Renderer::class);

        return $renderer->ad(app(AdRepository::class)->find($this->normalize($this->params->get('id'))));
    }

    /**
     * Return the frontend script and stylesheet tags.
     */
    public function assets()
    {
        return app(Renderer::class)->assets();
    }

    /**
     * Render a placement, or expose its ads when the tag is used as a pair.
     */
    private function renderPlacement(mixed $handle)
    {
        $renderer = app(Renderer::class);
        $handle = $this->normalize($handle);

        if (! $this->isPair) {
            return $renderer->placement($handle);
        }

        $placement = $renderer->resolvePlacement($handle);

        if (! $placement) {
            return $this->parseNoResults();
        }

        // Custom markup always chooses ads on the server, whatever the placement's
        // delivery setting, because the template decides how they're output.
        $context = DeliveryContext::fromRequest();
        $selector = app(AdSelector::class);
        $ads = $selector->choose($placement, $selector->candidates($placement, $context, includeUpcoming: false));
        $data = $renderer->placementData($placement, $context, $ads);

        if ($data['ads'] === []) {
            return $this->parseNoResults(['fallback' => $data['fallback']]);
        }

        $output = $this->parse([
            ...$data,
            'placement_attributes' => $this->placementAttributes($placement),
            'total_results' => count($data['ads']),
            'no_results' => false,
        ]);

        if (is_string($output) && config('billboard.frontend.auto_assets', true)) {
            $output .= $renderer->assets();
        }

        return $output;
    }

    /**
     * Build the data attributes for a server-rendered placement.
     */
    private function placementAttributes(Placement $placement): string
    {
        return sprintf(
            'data-billboard-placement="%s" data-billboard-mode="server"',
            e($placement->handle())
        );
    }

    /**
     * Reduce a tag parameter to a plain string handle or id.
     */
    private function normalize(mixed $value): ?string
    {
        if ($value instanceof Value) {
            $value = $value->value();
        }

        if ($value instanceof LabeledValue) {
            $value = $value->value();
        }

        if (is_array($value)) {
            $value = reset($value) ?: null;

            return $this->normalize($value);
        }

        return is_scalar($value) && (string) $value !== '' ? (string) $value : null;
    }
}
