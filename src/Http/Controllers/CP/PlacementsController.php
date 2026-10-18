<?php

namespace Billboard\Http\Controllers\CP;

use Billboard\Ads\Ad;
use Billboard\Ads\AdRepository;
use Billboard\Ads\AdStatus;
use Billboard\Blueprints\PlacementBlueprint;
use Billboard\Placements\Placement;
use Billboard\Placements\PlacementRepository;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Statamic\CP\Column;
use Statamic\CP\PublishForm;
use Statamic\Exceptions\NotFoundHttpException;
use Statamic\Facades\Action;
use Statamic\Http\Controllers\CP\CpController;

class PlacementsController extends CpController
{
    /**
     * Render the placements index with delivery mode and active ad counts.
     */
    public function index(PlacementRepository $placements, AdRepository $ads)
    {
        $this->authorize('manage billboard placements', [], __('You are not authorized to manage placements.'));

        $now = CarbonImmutable::now();
        $allAds = $ads->all();

        $items = $placements->all()->map(fn (Placement $placement) => [
            'id' => $placement->handle(),
            'title' => $placement->title(),
            'description' => $placement->description(),
            'tag' => $placement->tag(),
            'delivery' => $placement->delivery()->label(),
            'active_ads' => $allAds
                ->filter(fn (Ad $ad) => in_array($placement->handle(), $ad->placements(), true))
                ->filter(fn (Ad $ad) => $ad->status($now) === AdStatus::Active)
                ->count(),
            'edit_url' => $placement->editUrl(),
            'actions' => Action::for($placement),
        ])->values();

        return Inertia::render('billboard::PlacementsIndex', [
            'placements' => $items->all(),
            'columns' => [
                Column::make('title')->label(__('Title')),
                Column::make('tag')->label(__('Template tag'))->sortable(false),
                Column::make('delivery')->label(__('Chooses ads')),
                Column::make('active_ads')->label(__('Active ads'))->numeric(true),
            ],
            'actionUrl' => cp_route('billboard.placements.actions.run'),
            'createUrl' => cp_route('billboard.placements.create'),
            'assetsPublished' => is_file(public_path('vendor/billboard/frontend/billboard.js')),
        ]);
    }

    /**
     * Show the form for creating a placement.
     */
    public function create()
    {
        $this->authorize('manage billboard placements', [], __('You are not authorized to manage placements.'));

        return PublishForm::make(PlacementBlueprint::make(creating: true))
            ->icon('layout-sidebar-right')
            ->title(__('Create placement'))
            ->values(['delivery' => 'server', 'rotation' => 'weighted', 'max_ads' => 1])
            ->submittingTo(cp_route('billboard.placements.store'), 'POST');
    }

    /**
     * Validate and store a new placement, then redirect to its edit form.
     */
    public function store(Request $request, PlacementRepository $placements)
    {
        $this->authorize('manage billboard placements', [], __('You are not authorized to manage placements.'));

        $values = PublishForm::make(PlacementBlueprint::make(creating: true))->submit($request->all());
        $handle = (string) $values['handle'];

        if ($placements->exists($handle)) {
            throw ValidationException::withMessages(['handle' => __('billboard::messages.handle_taken')]);
        }

        unset($values['handle']);

        $placement = $placements->save($placements->make($handle, $values));

        return ['redirect' => $placement->editUrl()];
    }

    /**
     * Show the form for editing a placement.
     */
    public function edit(string $placement, PlacementRepository $placements)
    {
        $this->authorize('manage billboard placements', [], __('You are not authorized to manage placements.'));

        $placement = $placements->find($placement) ?? throw new NotFoundHttpException;

        return PublishForm::make(PlacementBlueprint::make())
            ->icon('layout-sidebar-right')
            ->title($placement->title())
            ->values($placement->data())
            ->submittingTo(cp_route('billboard.placements.update', $placement->handle()));
    }

    /**
     * Validate and save changes to an existing placement.
     */
    public function update(Request $request, string $placement, PlacementRepository $placements)
    {
        $this->authorize('manage billboard placements', [], __('You are not authorized to manage placements.'));

        $placement = $placements->find($placement) ?? throw new NotFoundHttpException;

        $values = PublishForm::make(PlacementBlueprint::make())->submit($request->all());
        unset($values['handle']);

        $placements->save($placement->merge($values));

        return ['title' => $placement->title()];
    }
}
