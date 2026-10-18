<?php

namespace Billboard\Actions;

use Billboard\Ads\Ad;
use Billboard\Ads\AdRepository;
use Statamic\Actions\Action;

class DeleteAd extends Action
{
    protected static $handle = 'billboard_delete_ad';

    protected $dangerous = true;

    protected $icon = 'trash';

    /**
     * Return the action label shown in the control panel.
     */
    public static function title()
    {
        return __('Delete');
    }

    /**
     * Show this action only for ads.
     */
    public function visibleTo($item)
    {
        return $item instanceof Ad;
    }

    /**
     * Allow the action for users who can delete ads.
     */
    public function authorize($user, $item)
    {
        return $user->can('delete billboard ads');
    }

    /**
     * Return the confirmation button label.
     */
    public function buttonText()
    {
        /** @translation */
        return 'Delete ad|Delete :count ads';
    }

    /**
     * Return the confirmation prompt for deleting ads.
     */
    public function confirmationText()
    {
        /** @translation */
        return 'Delete this ad? Its statistics are kept in reports.|Delete these :count ads? Their statistics are kept in reports.';
    }

    /**
     * Delete each selected ad and return a confirmation message.
     */
    public function run($items, $values)
    {
        $ads = app(AdRepository::class);

        $items->each(fn (Ad $ad) => $ads->delete($ad));

        return trans_choice('Ad deleted|:count ads deleted', $items->count());
    }
}
