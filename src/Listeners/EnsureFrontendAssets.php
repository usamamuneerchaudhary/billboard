<?php

namespace Billboard\Listeners;

use Billboard\Delivery\Renderer;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Http\Response;
use Statamic\Statamic;

class EnsureFrontendAssets
{
    /**
     * Create the listener with the placement renderer.
     */
    public function __construct(private Renderer $renderer) {}

    /**
     * Add the frontend script once, before the document's final body tag, when a placement reached the response without it.
     */
    public function handle(RequestHandled $event): void
    {
        $response = $event->response;

        if (Statamic::isCpRoute() || ! $response instanceof Response) {
            return;
        }

        $content = $response->getContent();

        if (! is_string($content)
            || ! str_contains($content, 'data-billboard-placement="')
            || str_contains($content, 'data-billboard-endpoint')) {
            return;
        }

        $assets = $this->renderer->assets(force: true);

        if ($assets === '') {
            return;
        }

        $closing = '</body>';
        $position = strrpos($content, $closing);

        if ($position === false) {
            $content .= $assets;
        } else {
            $content = substr_replace($content, $assets.$closing, $position, strlen($closing));
        }

        $response->setContent($content);
    }
}
