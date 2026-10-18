<?php

namespace Billboard\Blueprints;

use Billboard\Placements\Delivery;
use Billboard\Placements\Rotation;
use Statamic\Facades\Blueprint;
use Statamic\Fields\Blueprint as StatamicBlueprint;
use Statamic\Rules\Handle;

class PlacementBlueprint
{
    /**
     * Build the publish form blueprint for creating or editing a placement.
     */
    public static function make(bool $creating = false): StatamicBlueprint
    {
        $details = [
            'title' => [
                'type' => 'text',
                'display' => __('Title'),
                'validate' => ['required', 'max:120'],
            ],
        ];

        if ($creating) {
            $details['handle'] = [
                'type' => 'slug',
                'display' => __('Handle'),
                'instructions' => __('billboard::messages.placement_handle_instructions'),
                'separator' => '_',
                'from' => 'title',
                'validate' => ['required', 'max:64', new Handle],
            ];
        }

        $details['description'] = [
            'type' => 'textarea',
            'display' => __('Description'),
            'instructions' => __('billboard::messages.placement_description_instructions'),
            'validate' => ['max:500'],
        ];

        return Blueprint::make()->setContents([
            'tabs' => [
                'main' => [
                    'sections' => [
                        self::section(__('Details'), $details),
                        self::section(__('Rotation'), [
                            'delivery' => [
                                'type' => 'button_group',
                                'display' => __('Choose ads'),
                                'instructions' => __('billboard::messages.placement_delivery_instructions'),
                                'options' => collect(Delivery::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()])->all(),
                                'default' => Delivery::Server->value,
                                'validate' => ['required'],
                            ],
                            'rotation' => [
                                'type' => 'button_group',
                                'display' => __('Order'),
                                'instructions' => __('billboard::messages.placement_rotation_instructions'),
                                'options' => collect(Rotation::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()])->all(),
                                'default' => Rotation::Weighted->value,
                                'validate' => ['required'],
                            ],
                            'max_ads' => [
                                'type' => 'integer',
                                'display' => __('Ads shown at once'),
                                'instructions' => __('billboard::messages.placement_max_ads_instructions'),
                                'default' => 1,
                                'min' => 1,
                                'validate' => ['required', 'integer', 'min:1', 'max:10'],
                                'width' => 50,
                            ],
                        ]),
                        self::section(__('Appearance'), [
                            'width' => [
                                'type' => 'integer',
                                'display' => __('Width (px)'),
                                'instructions' => __('billboard::messages.placement_size_instructions'),
                                'validate' => ['nullable', 'integer', 'min:1', 'max:4000'],
                                'width' => 50,
                            ],
                            'height' => [
                                'type' => 'integer',
                                'display' => __('Height (px)'),
                                'validate' => ['nullable', 'integer', 'min:1', 'max:4000'],
                                'width' => 50,
                            ],
                            'label' => [
                                'type' => 'text',
                                'display' => __('Disclosure label'),
                                'instructions' => __('billboard::messages.placement_label_instructions'),
                                'validate' => ['max:60'],
                            ],
                            'fallback' => [
                                'type' => 'code',
                                'display' => __('Fallback HTML'),
                                'instructions' => __('billboard::messages.placement_fallback_instructions'),
                                'mode' => 'htmlmixed',
                                'validate' => ['max:65535'],
                            ],
                        ]),
                    ],
                ],
            ],
        ]);
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
