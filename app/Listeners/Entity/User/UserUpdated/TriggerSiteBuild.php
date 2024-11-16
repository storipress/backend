<?php

namespace App\Listeners\Entity\User\UserUpdated;

use App\Events\Entity\User\UserUpdated;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class TriggerSiteBuild implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(UserUpdated $event): void
    {
        $user = User::withoutEagerLoads()
            ->with(['tenants'])
            ->find($event->userId);

        if (! ($user instanceof User)) {
            return;
        }

        foreach ($user->tenants as $tenant) {
            if (! $tenant->initialized) {
                continue;
            }

            if ($tenant->is_ssr) {
                continue;
            }

            $tenant->run(function () use ($event) {
                build_site('user:update', [
                    'id' => $event->userId,
                ]);
            });
        }
    }
}
