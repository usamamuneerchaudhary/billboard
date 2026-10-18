<?php

namespace Billboard\Ads;

use Billboard\Events\AdDeleted;
use Billboard\Events\AdSaved;
use Billboard\Stores\YamlDirectory;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AdRepository
{
    /**
     * Create the repository with the YAML store.
     */
    public function __construct(private YamlDirectory $store)
    {
    }

    /**
     * Return every ad, sorted by title.
     *
     * @return Collection<string, Ad>
     */
    public function all(): Collection
    {
        return collect($this->store->all())
            ->map(fn (array $data, string $id) => new Ad($id, $data))
            ->sortBy(fn (Ad $ad) => mb_strtolower($ad->title()))
            ->keyBy(fn (Ad $ad) => $ad->id());
    }

    /**
     * Load an ad by id.
     */
    public function find(?string $id): ?Ad
    {
        if ($id === null || ! preg_match(YamlDirectory::KEY_PATTERN, $id)) {
            return null;
        }

        $data = $this->store->get($id);

        return $data === null ? null : new Ad($id, $data);
    }

    /**
     * Build an unsaved ad with a new id.
     *
     * @param  array<string, mixed>  $data
     */
    public function make(array $data = []): Ad
    {
        return new Ad((string) Str::uuid(), $data);
    }

    /**
     * Persist an ad and dispatch the saved event.
     */
    public function save(Ad $ad): Ad
    {
        $data = array_filter(
            [...$ad->data(), 'updated_at' => now()->timestamp],
            fn ($value) => $value !== null && $value !== [] && $value !== ''
        );

        $this->store->put($ad->id(), $data);

        event(new AdSaved($ad));

        return $ad;
    }

    /**
     * Delete an ad and dispatch the deleted event.
     */
    public function delete(Ad $ad): void
    {
        $this->store->delete($ad->id());

        event(new AdDeleted($ad));
    }

    /**
     * Remove a placement handle from every ad that references it.
     */
    public function detachPlacement(string $handle): void
    {
        $this->all()
            ->filter(fn (Ad $ad) => in_array($handle, $ad->placements(), true))
            ->each(fn (Ad $ad) => $this->save(
                $ad->setPlacements(array_diff($ad->placements(), [$handle]))
            ));
    }
}
