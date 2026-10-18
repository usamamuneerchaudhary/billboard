<?php

namespace Billboard\Listeners;

use Statamic\Events\EntryBlueprintFound;
use Statamic\Events\GlobalVariablesBlueprintFound;
use Statamic\Events\TermBlueprintFound;
use Statamic\Fields\Blueprint;

use function Statamic\trans as __;

class InjectAdvertisementSets
{
    private bool $changed = false;

    /**
     * Add the advertisement set to an entry blueprint.
     */
    public function handleEntry(EntryBlueprintFound $event): void
    {
        $this->inject($event->blueprint);
    }

    /**
     * Add the advertisement set to a taxonomy term blueprint.
     */
    public function handleTerm(TermBlueprintFound $event): void
    {
        $this->inject($event->blueprint);
    }

    /**
     * Add the advertisement set to a global set blueprint.
     */
    public function handleGlobal(GlobalVariablesBlueprintFound $event): void
    {
        $this->inject($event->blueprint);
    }

    /**
     * Add an Advertisement set to every Bard and Replicator field when content injection is enabled.
     */
    public function inject(Blueprint $blueprint): void
    {
        if (! config('billboard.content.inject_sets')) {
            return;
        }

        $contents = $blueprint->contents();
        $this->changed = false;
        $tabs = $this->tabs($contents['tabs'] ?? []);

        if (! $this->changed) {
            return;
        }

        $contents['tabs'] = $tabs;
        $blueprint->setContents($contents);
    }

    /**
     * Walk blueprint tabs and return them with advertisement sets inserted.
     *
     * @param  array<string, mixed>  $tabs
     * @return array<string, mixed>
     */
    private function tabs(array $tabs): array
    {
        foreach ($tabs as $handle => $tab) {
            if (! is_array($tab)) {
                continue;
            }

            if (isset($tab['sections']) && is_array($tab['sections'])) {
                foreach ($tab['sections'] as $index => $section) {
                    if (! is_array($section)) {
                        continue;
                    }

                    $section['fields'] = $this->fields($section['fields'] ?? []);
                    $tab['sections'][$index] = $section;
                }
            }

            if (isset($tab['fields']) && is_array($tab['fields'])) {
                $tab['fields'] = $this->fields($tab['fields']);
            }

            $tabs[$handle] = $tab;
        }

        return $tabs;
    }

    /**
     * Walk a field list and insert the set into Bard and Replicator fields.
     *
     * @param  array<int, mixed>  $fields
     * @return array<int, mixed>
     */
    private function fields(array $fields): array
    {
        foreach ($fields as $index => $field) {
            if (! is_array($field) || ! isset($field['field']) || ! is_array($field['field'])) {
                continue;
            }

            $field['field'] = $this->field($field['field']);
            $fields[$index] = $field;
        }

        return $fields;
    }

    /**
     * Insert the set into one field config, then walk any nested fields.
     *
     * @param  array<string, mixed>  $field
     * @return array<string, mixed>
     */
    private function field(array $field): array
    {
        if (($field['billboard'] ?? true) === false) {
            return $field;
        }

        if (in_array($field['type'] ?? null, ['bard', 'replicator'], true)) {
            $field['sets'] = $this->withAdvertisement($field['sets'] ?? []);
        }

        if (isset($field['sets']) && is_array($field['sets'])) {
            $field['sets'] = $this->sets($field['sets']);
        }

        if (isset($field['fields']) && is_array($field['fields'])) {
            $field['fields'] = $this->fields($field['fields']);
        }

        return $field;
    }

    /**
     * Walk Bard or Replicator sets, including grouped sets.
     *
     * @param  array<string, mixed>  $sets
     * @return array<string, mixed>
     */
    private function sets(array $sets): array
    {
        foreach ($sets as $handle => $set) {
            if (! is_array($set)) {
                continue;
            }

            if (isset($set['fields']) && is_array($set['fields'])) {
                $set['fields'] = $this->fields($set['fields']);
            }

            if (isset($set['sets']) && is_array($set['sets'])) {
                $set['sets'] = $this->sets($set['sets']);
            }

            $sets[$handle] = $set;
        }

        return $sets;
    }

    /**
     * Add the advertisement set when this field does not already have one.
     *
     * @param  array<string, mixed>  $sets
     * @return array<string, mixed>
     */
    private function withAdvertisement(array $sets): array
    {
        if (isset($sets['advertisement'])) {
            return $sets;
        }

        $this->changed = true;
        $sets['advertisement'] = [
            'display' => __('Advertisement'),
            'icon' => 'megaphone',
            'fields' => [
                [
                    'handle' => 'placement',
                    'field' => [
                        'type' => 'billboard',
                        'display' => __('Placement'),
                        'instructions' => __('billboard::messages.content_placement_instructions'),
                        'validate' => ['required'],
                        'max_items' => 1,
                        'dictionary' => ['type' => 'ad_placements'],
                    ],
                ],
            ],
        ];

        return $sets;
    }
}
