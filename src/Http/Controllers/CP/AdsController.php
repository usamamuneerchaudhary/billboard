<?php

namespace Billboard\Http\Controllers\CP;

use Billboard\Ads\Ad;
use Billboard\Ads\AdRepository;
use Billboard\Ads\AdType;
use Billboard\Blueprints\AdBlueprint;
use Billboard\Placements\PlacementRepository;
use Billboard\Tracking\StatsRepository;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Statamic\CP\Column;
use Statamic\CP\PublishForm;
use Statamic\Exceptions\NotFoundHttpException;
use Statamic\Facades\Action;
use Statamic\Facades\User;
use Statamic\Http\Controllers\CP\CpController;

class AdsController extends CpController
{
    /**
     * Render the ads index with status, placement, and recent totals.
     */
    public function index(AdRepository $ads, PlacementRepository $placements, StatsRepository $stats)
    {
        $this->authorize('view billboard ads', [], __('You are not authorized to view ads.'));

        $now = CarbonImmutable::now();
        $reporting = $stats->available();
        $totals = $reporting ? $stats->byAd($now->subDays(29)->startOfDay(), $now) : [];
        $placementTitles = $placements->all()->map->title();

        $columns = [
            Column::make('title')->label(__('Title')),
            Column::make('status')->label(__('Status')),
            Column::make('type')->label(__('Type')),
            Column::make('placements')->label(__('Placements'))->sortable(false),
            Column::make('weight')->label(__('Weight'))->numeric(true),
        ];

        if ($reporting) {
            $columns[] = Column::make('impressions')->label(__('Impressions (30 days)'))->numeric(true);
            $columns[] = Column::make('clicks')->label(__('Clicks (30 days)'))->numeric(true);
        }

        $items = $ads->all()->map(function (Ad $ad) use ($now, $totals, $placementTitles) {
            $status = $ad->status($now);
            $total = $totals[$ad->id()] ?? ['impressions' => 0, 'clicks' => 0];

            return [
                'id' => $ad->id(),
                'title' => $ad->title(),
                'status' => $status->label(),
                'status_color' => $status->color(),
                'type' => $ad->type()->label(),
                'placements' => collect($ad->placements())
                    ->map(fn (string $handle) => $placementTitles->get($handle))
                    ->filter()
                    ->implode(', '),
                'weight' => $ad->weight(),
                'impressions' => $total['impressions'],
                'clicks' => $total['clicks'],
                'edit_url' => $ad->editUrl(),
                'report_url' => $ad->reportUrl(),
                'actions' => Action::for($ad),
            ];
        })->values();

        return Inertia::render('billboard::AdsIndex', [
            'ads' => $items->all(),
            'columns' => $columns,
            'actionUrl' => cp_route('billboard.ads.actions.run'),
            'canCreate' => User::current()->can('create billboard ads'),
            'createUrl' => cp_route('billboard.ads.create'),
            'canViewReports' => User::current()->can('view billboard reports'),
            'hasPlacements' => $placementTitles->isNotEmpty(),
            'canManagePlacements' => User::current()->can('manage billboard placements'),
            'createPlacementUrl' => cp_route('billboard.placements.create'),
        ]);
    }

    /**
     * Show the form for creating an ad.
     */
    public function create()
    {
        $this->authorize('create billboard ads', [], __('You are not authorized to create ads.'));

        return PublishForm::make(AdBlueprint::make($this->canUseHtml()))
            ->icon('megaphone')
            ->title(__('Create ad'))
            ->values(['type' => AdType::Image->value, 'enabled' => true, 'weight' => 10])
            ->submittingTo(cp_route('billboard.ads.store'), 'POST');
    }

    /**
     * Validate and store a new ad, then redirect to its edit form.
     */
    public function store(Request $request, AdRepository $ads)
    {
        $this->authorize('create billboard ads', [], __('You are not authorized to create ads.'));

        $values = PublishForm::make(AdBlueprint::make($this->canUseHtml()))->submit($request->all());

        $this->guardHtml($values['type'] ?? null);

        $ad = $ads->save($ads->make($this->clean($values)));

        return ['redirect' => $ad->editUrl()];
    }

    /**
     * Show the form for editing an ad.
     */
    public function edit(string $ad, AdRepository $ads)
    {
        $this->authorize('edit billboard ads', [], __('You are not authorized to edit ads.'));

        $ad = $this->findOrFail($ads, $ad);
        $locked = $ad->type() === AdType::Html && ! $this->canUseHtml();

        return PublishForm::make(AdBlueprint::make(true))
            ->icon('megaphone')
            ->title($ad->title())
            ->values($ad->data())
            ->readOnly($locked)
            ->submittingTo(cp_route('billboard.ads.update', $ad->id()));
    }

    /**
     * Validate and save changes to an existing ad.
     */
    public function update(Request $request, string $ad, AdRepository $ads)
    {
        $this->authorize('edit billboard ads', [], __('You are not authorized to edit ads.'));

        $ad = $this->findOrFail($ads, $ad);

        if ($ad->type() === AdType::Html) {
            $this->guardHtml(AdType::Html->value);
        }

        $values = PublishForm::make(AdBlueprint::make($this->canUseHtml()))->submit($request->all());

        $this->guardHtml($values['type'] ?? null);

        $ads->save($ad->merge($this->clean($values)));

        return ['title' => $ad->title()];
    }

    /**
     * Load an ad or abort with a not found response.
     */
    private function findOrFail(AdRepository $ads, string $id): Ad
    {
        return $ads->find($id) ?? throw new NotFoundHttpException;
    }

    /**
     * Decide whether the current user may edit HTML ads.
     */
    private function canUseHtml(): bool
    {
        return User::current()->can('edit billboard html ads');
    }

    /**
     * Reject HTML ads when the current user lacks permission.
     */
    private function guardHtml(?string $type): void
    {
        if ($type === AdType::Html->value && ! $this->canUseHtml()) {
            throw ValidationException::withMessages([
                'type' => __('billboard::messages.html_permission_required'),
            ]);
        }
    }

    /**
     * Drop fields that don't belong to the chosen ad type so stale values
     * (an old image on what is now a text ad) don't linger in the file.
     */
    private function clean(array $values): array
    {
        $byType = [
            AdType::Image->value => ['image', 'image_url', 'alt'],
            AdType::Text->value => ['headline', 'body', 'button_text'],
            AdType::Html->value => ['html'],
        ];

        $type = $values['type'] ?? AdType::Image->value;

        foreach ($byType as $key => $fields) {
            if ($key !== $type) {
                foreach ($fields as $field) {
                    $values[$field] = null;
                }
            }
        }

        return $values;
    }
}
