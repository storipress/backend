<?php

namespace App\Listeners\Entity\Account\AccountDeleted;

use App\Events\Entity\Account\AccountDeleted;
use App\Models\Tenants\User as TenantUser;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class RevokeJoinedTenants implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(AccountDeleted $event): void
    {
        $user = User::with(['tenants', 'publications'])->find($event->userId);

        if (!($user instanceof User)) {
            return;
        }

        $joined = $user->tenants->diff($user->publications);

        $user->tenants()->detach($joined->pluck('id')->toArray());

        tenancy()->runForMultiple(
            $joined,
            function () use ($user) {
                TenantUser::find($user->id)?->delete();
            },
        );
    }
}
