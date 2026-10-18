<?php

namespace Billboard\Placements;

use Billboard\Ads\AdRepository;
use Billboard\Events\PlacementDeleted;
use Billboard\Events\PlacementSaved;
use Billboard\Stores\YamlDirectory;
use Illuminate\Support\Collection;

class PlacementRepository
{
    /**
     * Create the repository with the YAML store and ad repository.
     */
    public function __construct(private YamlDirectory $store, private AdRepository $ads) {}

    /**
     * Return every placement, sorted by title.
     *
     * @return Collection<string, Placement>
     */
    public function all(): Collection
    {
        return collect($this->store->all())
            ->map(fn (array $data, string $handle) => new Placement($handle, $data))
            ->sortBy(fn (Placement $placement) => mb_strtolower($placement->title()))
            ->keyBy(fn (Placement $placement) => $placement->handle());
    }

    /**
     * Load a placement by handle.
     */
    public function find(?string $handle): ?Placement
    {
        if ($handle === null || ! preg_match(YamlDirectory::KEY_PATTERN, $handle)) {
            return null;
        }

        $data = $this->store->get($handle);

        return $data === null ? null : new Placement($handle, $data);
    }

    /**
     * Decide whether a placement handle already exists.
     */
    public function exists(string $handle): bool
    {
        return $this->store->exists($handle);
    }

    /**
     * Build an unsaved placement from a handle and values.
     *
     * @param  array<string, mixed>  $data
     */
    public function make(string $handle, array $data = []): Placement
    {
        return new Placement($handle, $data);
    }

    /**
     * Persist a placement and dispatch the saved event.
     */
    public function save(Placement $placement): Placement
    {
        $data = array_filter(
            $placement->data(),
            fn ($value) => $value !== null && $value !== [] && $value !== ''
        );

        $this->store->put($placement->handle(), $data);

        event(new PlacementSaved($placement));

        return $placement;
    }

    /**
     * Delete a placement, detach it from ads, and dispatch the deleted event.
     */
    public function delete(Placement $placement): void
    {
        $this->store->delete($placement->handle());

        $this->ads->detachPlacement($placement->handle());

        event(new PlacementDeleted($placement));
    }
}
