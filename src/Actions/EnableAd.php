<?php

namespace Billboard\Actions;

use Billboard\Ads\Ad;
use Billboard\Ads\AdRepository;
use Statamic\Actions\Action;

class EnableAd extends Action
{
    protected static $handle = 'billboard_enable_ad';

    protected $confirm = false;

    protected $icon = 'checkmark';

    /**
     * Return the action label shown in the control panel.
     */
    public static function title()
    {
        return __('Enable');
    }

    /**
     * Show this action only for ads that are currently disabled.
     */
    public function visibleTo($item)
    {
        return $item instanceof Ad && ! $item->enabled();
    }

    /**
     * Allow the bulk action when every selected item is an ad.
     */
    public function visibleToBulk($items)
    {
        return $items->every(fn ($item) => $item instanceof Ad);
    }

    /**
     * Allow the action for users who can edit ads.
     */
    public function authorize($user, $item)
    {
        return $user->can('edit billboard ads');
    }

    /**
     * Enable each selected ad and return a confirmation message.
     */
    public function run($items, $values)
    {
        $ads = app(AdRepository::class);

        $items->each(fn (Ad $ad) => $ads->save($ad->merge(['enabled' => true])));

        return trans_choice('Ad enabled|:count ads enabled', $items->count());
    }
}
