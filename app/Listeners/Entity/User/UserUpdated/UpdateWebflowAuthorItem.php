<?php

namespace App\Listeners\Entity\User\UserUpdated;

use App\Events\Entity\User\UserUpdated;
use App\Jobs\Webflow\SyncUserToWebflow;
use App\Models\User;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class UpdateWebflowAuthorItem implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(UserUpdated $event): void
    {
        $user = User::withoutEagerLoads()
            ->with([
                'tenants' => function (Builder $query) {
                    $query->where('initialized', '=', true);
                },
            ])
            ->find($event->userId);

        if (! ($user instanceof User)) {
            return;
        }

        foreach ($user->tenants as $tenant) {
            SyncUserToWebflow::dispatch(
                $tenant->id,
                $user->id,
            );
        }
    }
}
