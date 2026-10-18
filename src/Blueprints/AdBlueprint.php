<?php

namespace Billboard\Blueprints;

use Billboard\Ads\AdType;
use Billboard\Rules\SafeUrl;
use Statamic\Facades\AssetContainer;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Site;
use Statamic\Fields\Blueprint as StatamicBlueprint;

class AdBlueprint
{
    /**
     * Build the publish form blueprint for creating or editing an ad.
     */
    public static function make(bool $canUseHtml = true): StatamicBlueprint
    {
        $types = collect(AdType::cases())
            ->reject(fn (AdType $type) => $type === AdType::Html && ! $canUseHtml)
            ->mapWithKeys(fn (AdType $type) => [$type->value => $type->label()])
            ->all();

        return Blueprint::make()->setContents([
            'tabs' => [
                'main' => [
                    'display' => __('Ad'),
                    'sections' => [
                        self::section(__('Creative'), [
                            'title' => [
                                'type' => 'text',
                                'display' => __('Title'),
                                'instructions' => __('billboard::messages.ad_title_instructions'),
                                'validate' => ['required', 'max:200'],
                            ],
                            'type' => [
                                'type' => 'button_group',
                                'display' => __('Type'),
                                'instructions' => __('billboard::messages.ad_type_instructions'),
                                'options' => $types,
                                'default' => AdType::Image->value,
                                'validate' => ['required', 'in:'.implode(',', array_keys($types))],
                            ],
                            ...self::imageFields(),
                            'alt' => [
                                'type' => 'text',
                                'display' => __('Alternative text'),
                                'instructions' => __('billboard::messages.ad_alt_instructions'),
                                'validate' => ['required_if:type,image', 'max:250'],
                                'if' => ['type' => 'equals image'],
                            ],
                            'headline' => [
                                'type' => 'text',
                                'display' => __('Headline'),
                                'validate' => ['required_if:type,text', 'max:120'],
                                'if' => ['type' => 'equals text'],
                            ],
                            'body' => [
                                'type' => 'textarea',
                                'display' => __('Body'),
                                'validate' => ['max:500'],
                                'if' => ['type' => 'equals text'],
                            ],
                            'button_text' => [
                                'type' => 'text',
                                'display' => __('Button text'),
                                'validate' => ['max:40'],
                                'width' => 50,
                                'if' => ['type' => 'equals text'],
                            ],
                            'html' => [
                                'type' => 'code',
                                'display' => __('HTML'),
                                'instructions' => __('billboard::messages.ad_html_instructions'),
                                'mode' => 'htmlmixed',
                                'validate' => ['required_if:type,html', 'max:65535'],
                                'if' => ['type' => 'equals html'],
                            ],
                        ]),
                        self::section(__('Link'), [
                            'url' => [
                                'type' => 'text',
                                'input_type' => 'url',
                                'display' => __('Destination URL'),
                                'instructions' => __('billboard::messages.ad_url_instructions'),
                                'validate' => ['required_unless:type,html', 'nullable', new SafeUrl],
                            ],
                            'new_tab' => [
                                'type' => 'toggle',
                                'display' => __('Open in a new tab'),
                                'instructions' => __('billboard::messages.ad_new_tab_instructions'),
                                'default' => false,
                            ],
                        ]),
                    ],
                ],
                'delivery' => [
                    'display' => __('Placement & schedule'),
                    'sections' => [
                        self::section(__('Where'), [
                            'enabled' => [
                                'type' => 'toggle',
                                'display' => __('Enabled'),
                                'default' => true,
                            ],
                            'placements' => [
                                'type' => 'dictionary',
                                'dictionary' => 'ad_placements',
                                'display' => __('Placements'),
                                'instructions' => __('billboard::messages.ad_placements_instructions'),
                                'validate' => ['array'],
                            ],
                            'weight' => [
                                'type' => 'integer',
                                'display' => __('Weight'),
                                'instructions' => __('billboard::messages.ad_weight_instructions'),
                                'default' => 10,
                                'min' => 1,
                                'validate' => ['required', 'integer', 'min:1', 'max:100'],
                                'width' => 50,
                            ],
                            ...self::siteFields(),
                        ]),
                        self::section(__('When'), [
                            'start_at' => [
                                'type' => 'date',
                                'display' => __('Start'),
                                'instructions' => __('billboard::messages.ad_start_instructions'),
                                'time_enabled' => true,
                                'width' => 50,
                            ],
                            'end_at' => [
                                'type' => 'date',
                                'display' => __('End'),
                                'instructions' => __('billboard::messages.ad_end_instructions'),
                                'time_enabled' => true,
                                'width' => 50,
                            ],
                        ]),
                    ],
                ],
                'targeting' => [
                    'display' => __('Targeting & limits'),
                    'sections' => [
                        self::section(__('Pages & devices'), [
                            'include_paths' => [
                                'type' => 'list',
                                'display' => __('Only on these paths'),
                                'instructions' => __('billboard::messages.ad_include_paths_instructions'),
                                'width' => 50,
                            ],
                            'exclude_paths' => [
                                'type' => 'list',
                                'display' => __('Never on these paths'),
                                'instructions' => __('billboard::messages.ad_exclude_paths_instructions'),
                                'width' => 50,
                            ],
                            'devices' => [
                                'type' => 'checkboxes',
                                'display' => __('Devices'),
                                'instructions' => __('billboard::messages.ad_devices_instructions'),
                                'options' => ['desktop' => __('Desktop'), 'mobile' => __('Mobile')],
                                'default' => ['desktop', 'mobile'],
                                'inline' => true,
                                'validate' => ['required', 'array', 'min:1'],
                            ],
                        ]),
                        self::section(__('Limits'), [
                            'max_impressions' => [
                                'type' => 'integer',
                                'display' => __('Maximum impressions'),
                                'instructions' => __('billboard::messages.ad_max_impressions_instructions'),
                                'validate' => ['nullable', 'integer', 'min:1'],
                                'width' => 50,
                            ],
                            'max_clicks' => [
                                'type' => 'integer',
                                'display' => __('Maximum clicks'),
                                'instructions' => __('billboard::messages.ad_max_clicks_instructions'),
                                'validate' => ['nullable', 'integer', 'min:1'],
                                'width' => 50,
                            ],
                            'frequency_cap' => [
                                'type' => 'integer',
                                'display' => __('Views per visitor per day'),
                                'instructions' => __('billboard::messages.ad_frequency_cap_instructions'),
                                'validate' => ['nullable', 'integer', 'min:1', 'max:100'],
                                'width' => 50,
                            ],
                        ]),
                    ],
                ],
            ],
        ]);
    }

    /**
     * Use the asset picker when the site has asset containers, otherwise
     * fall back to a plain URL so image ads still work.
     */
    private static function imageFields(): array
    {
        $configured = config('billboard.assets.container');
        $container = $configured ? AssetContainer::find($configured) : AssetContainer::all()->first();

        if (! $container) {
            return [
                'image_url' => [
                    'type' => 'text',
                    'input_type' => 'url',
                    'display' => __('Image URL'),
                    'instructions' => __('billboard::messages.ad_image_url_instructions'),
                    'validate' => ['required_if:type,image', 'nullable', new SafeUrl],
                    'if' => ['type' => 'equals image'],
                ],
            ];
        }

        return [
            'image' => [
                'type' => 'assets',
                'display' => __('Image'),
                'instructions' => __('billboard::messages.ad_image_instructions'),
                'container' => $container->handle(),
                'folder' => config('billboard.assets.folder'),
                'max_files' => 1,
                'mode' => 'grid',
                'validate' => ['required_if:type,image', 'image'],
                'if' => ['type' => 'equals image'],
            ],
        ];
    }

    /**
     * Return a sites field when the site is multisite, otherwise nothing.
     */
    private static function siteFields(): array
    {
        if (! Site::hasMultiple()) {
            return [];
        }

        return [
            'sites' => [
                'type' => 'sites',
                'display' => __('Sites'),
                'instructions' => __('billboard::messages.ad_sites_instructions'),
                'mode' => 'select',
                'width' => 50,
            ],
        ];
    }

    /**
     * Build a blueprint section from a display name and field definitions.
     */
    private static function section(string $display, array $fields): array
    {
        return [
            'display' => $display,
            'fields' => collect($fields)
                ->map(fn (array $field, string $handle) => ['handle' => $handle, 'field' => $field])
                ->values()
                ->all(),
        ];
    }
}
