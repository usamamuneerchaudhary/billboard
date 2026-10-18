<?php

namespace Billboard\Content;

use Billboard\Delivery\Renderer;
use League\CommonMark\Event\DocumentRenderedEvent;
use League\CommonMark\Output\RenderedContent;
use Statamic\Statamic;

class Shortcodes
{
    /**
     * Create the expander with the placement renderer.
     */
    public function __construct(private Renderer $renderer) {}

    /**
     * Replace Billboard shortcodes in rendered Markdown, leaving control panel screens and code samples unchanged.
     */
    public function expand(string $html): string
    {
        if (Statamic::isCpRoute()) {
            return $html;
        }

        [$html, $literals] = $this->shieldCode($html);
        $pattern = '\{\{\s*billboard:([A-Za-z][A-Za-z0-9_-]*)\s*\}\}|\[billboard:([A-Za-z][A-Za-z0-9_-]*)\]';

        $html = preg_replace_callback(
            '/<p>\s*(?:'.$pattern.')\s*<\/p>/',
            fn (array $matches) => $this->render($matches),
            $html,
        ) ?? $html;

        $html = preg_replace_callback(
            '/'.$pattern.'/',
            fn (array $matches) => $this->render($matches),
            $html,
        ) ?? $html;

        return strtr($html, $literals);
    }

    /**
     * Swap pre and code blocks for tokens so shortcodes inside them stay text.
     *
     * @return array{0: string, 1: array<string, string>}
     */
    private function shieldCode(string $html): array
    {
        $literals = [];

        $shielded = preg_replace_callback(
            '/<(pre|code)\b[^>]*>.*?<\/\1>/is',
            function (array $matches) use (&$literals) {
                $token = '%%BILLBOARD_LITERAL_'.count($literals).'%%';
                $literals[$token] = $matches[0];

                return $token;
            },
            $html,
        );

        return [$shielded ?? $html, $shielded === null ? [] : $literals];
    }

    /**
     * Expand shortcodes in a CommonMark document after it has been rendered to HTML.
     */
    public function expandRendered(DocumentRenderedEvent $event): void
    {
        $output = $event->getOutput();
        $html = $this->expand($output->getContent());

        if ($html === $output->getContent()) {
            return;
        }

        $event->replaceOutput(new RenderedContent($output->getDocument(), $html));
    }

    /**
     * Render the placement named by a shortcode match.
     */
    private function render(array $matches): string
    {
        $handle = $matches[1] !== '' ? $matches[1] : $matches[2];

        return $this->renderer->placement($handle);
    }
}
