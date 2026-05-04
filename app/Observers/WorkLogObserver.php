<?php

namespace App\Observers;

use App\Models\WorkLog;

class WorkLogObserver
{
    /**
     * Handle the WorkLog "created" event.
     */
    public function created(WorkLog $workLog): void
    {
        $building = $workLog->building;
        if ($building) {
            $building->actual_expenses += $workLog->calculated_cost;
            $building->save();
        }
    }

    /**
     * Handle the WorkLog "updated" event.
     */
    public function updated(WorkLog $workLog): void
    {
        //
    }

    /**
     * Handle the WorkLog "deleted" event.
     */
    public function deleted(WorkLog $workLog): void
    {
        //
    }

    /**
     * Handle the WorkLog "restored" event.
     */
    public function restored(WorkLog $workLog): void
    {
        //
    }

    /**
     * Handle the WorkLog "force deleted" event.
     */
    public function forceDeleted(WorkLog $workLog): void
    {
        //
    }
}
