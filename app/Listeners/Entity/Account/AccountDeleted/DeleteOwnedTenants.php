<?php

namespace App\Listeners\Entity\Account\AccountDeleted;

use App\Events\Entity\Account\AccountDeleted;
use App\Events\Entity\Tenant\TenantDeleted;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class DeleteOwnedTenants implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(AccountDeleted $event): void
    {
        $user = User::with(['publications'])->find($event->userId);

        if (! ($user instanceof User)) {
            return;
        }

        foreach ($user->publications as $publication) {
            $publication->delete();

            TenantDeleted::dispatch($publication->id);
        }
    }
}
