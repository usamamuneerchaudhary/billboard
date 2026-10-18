<?php

namespace Billboard\Actions;

use Billboard\Placements\Placement;
use Billboard\Placements\PlacementRepository;
use Statamic\Actions\Action;

class DeletePlacement extends Action
{
    protected static $handle = 'billboard_delete_placement';

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
     * Show this action only for placements.
     */
    public function visibleTo($item)
    {
        return $item instanceof Placement;
    }

    /**
     * Allow the action for users who can manage placements.
     */
    public function authorize($user, $item)
    {
        return $user->can('manage billboard placements');
    }

    /**
     * Return the confirmation button label.
     */
    public function buttonText()
    {
        /** @translation */
        return 'Delete placement|Delete :count placements';
    }

    /**
     * Return the warning shown before placements are deleted.
     */
    public function warningText()
    {
        /** @translation */
        return 'Ads assigned to it are kept but removed from it, and its template tag will stop outputting anything.';
    }

    /**
     * Return the confirmation prompt for deleting placements.
     */
    public function confirmationText()
    {
        /** @translation */
        return 'Delete this placement?|Delete these :count placements?';
    }

    /**
     * Delete each selected placement and return a confirmation message.
     */
    public function run($items, $values)
    {
        $placements = app(PlacementRepository::class);

        $items->each(fn (Placement $placement) => $placements->delete($placement));

        return trans_choice('Placement deleted|:count placements deleted', $items->count());
    }
}
