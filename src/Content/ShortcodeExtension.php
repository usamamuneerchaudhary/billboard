<?php

namespace Billboard\Content;

use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Event\DocumentRenderedEvent;
use League\CommonMark\Extension\ExtensionInterface;

class ShortcodeExtension implements ExtensionInterface
{
    /**
     * Expand Billboard shortcodes once Markdown has been turned into HTML.
     */
    public function register(EnvironmentBuilderInterface $environment): void
    {
        $environment->addEventListener(DocumentRenderedEvent::class, function (DocumentRenderedEvent $event) {
            app(Shortcodes::class)->expandRendered($event);
        });
    }
}
