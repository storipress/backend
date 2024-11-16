<?php

namespace App\Observers;

use App\Enums\Release\State;
use App\Models\Tenants\Release;
use App\Models\Tenants\ReleaseEvent;

class ReleaseEventsResetObserver
{
    /**
     * Handle the "updated" event.
     */
    public function updated(Release $release): void
    {
        if (! $release->wasChanged('state')) {
            return;
        }

        if ($release->state->notIn([State::aborted(), State::canceled(), State::error()])) {
            return;
        }

        $events = ReleaseEvent::where('release_id', $release->id);

        // generator error
        if ($release->state->is(State::error())) {
            $events->increment('attempts');
        }

        $events->update([
            'release_id' => null,
        ]);
    }
}
