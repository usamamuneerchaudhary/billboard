<?php

namespace Billboard\Fieldtypes;

use Billboard\Delivery\Renderer;
use Statamic\Dictionaries\Dictionary as DictionaryInstance;
use Statamic\Exceptions\DictionaryNotFoundException;
use Statamic\Facades\Dictionary as Dictionaries;
use Statamic\Facades\GraphQL;
use Statamic\Fieldtypes\Dictionary;

use function Statamic\trans as __;

class BillboardFieldtype extends Dictionary
{
    protected static $title = 'Advertisement';

    protected $categories = ['special'];

    protected $icon = 'megaphone';

    protected $selectableInForms = false;

    /**
     * Offer a placeholder and nothing else, so the publish form stays a single placement picker.
     */
    protected function configFieldItems(): array
    {
        return [
            [
                'display' => __('Appearance'),
                'fields' => [
                    'placeholder' => [
                        'display' => __('Placeholder'),
                        'instructions' => __('statamic::fieldtypes.select.config.placeholder'),
                        'type' => 'text',
                        'default' => '',
                    ],
                ],
            ],
        ];
    }

    /**
     * Return the search words used to find this fieldtype in the blueprint builder.
     */
    public function keywords(): array
    {
        return ['ad', 'advertisement', 'placement', 'billboard', 'banner'];
    }

    /**
     * Always list Billboard placements, whichever dictionary the field config mentions.
     */
    public function dictionary(): DictionaryInstance
    {
        $dictionary = Dictionaries::find('ad_placements');

        if ($dictionary) {
            return $dictionary;
        }

        throw new DictionaryNotFoundException('ad_placements');
    }

    /**
     * Keep the picker to one placement.
     */
    protected function multiple(): bool
    {
        return false;
    }

    /**
     * Store the placement handle, or null when the field is empty.
     */
    public function process($data)
    {
        return $this->placementHandle($data);
    }

    /**
     * Show the placement title in entry listings.
     */
    public function preProcessIndex($data)
    {
        $handle = $this->placementHandle($data);

        if ($handle === null) {
            return null;
        }

        return $this->dictionary()->get($handle)?->label() ?? $handle;
    }

    /**
     * Render the chosen placement.
     */
    public function augment($value)
    {
        $handle = $this->placementHandle($value);

        if ($handle === null) {
            return '';
        }

        return app(Renderer::class)->placement($handle);
    }

    /**
     * Expose the rendered placement as a string in GraphQL.
     */
    public function toGqlType()
    {
        return [
            'type' => GraphQL::string(),
            'resolve' => function ($item, $args, $context, $info) {
                return $item->resolveGqlValue($info->fieldName);
            },
        ];
    }

    /**
     * Reduce a stored field value to a placement handle.
     */
    private function placementHandle(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = $value['value'] ?? reset($value);
        }

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
