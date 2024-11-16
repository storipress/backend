<?php

namespace App\Listeners\Entity\Account\AvatarRemoved;

use App\Events\Entity\Account\AvatarRemoved;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Segment\Segment;

class RecordUserAction implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(AvatarRemoved $event): void
    {
        $user = User::withoutEagerLoads()
            ->with(['tenants'])
            ->find($event->userId);

        if (! ($user instanceof User)) {
            return;
        }

        foreach ($user->tenants as $tenant) {
            Segment::track([
                'userId' => (string) $user->id,
                'event' => 'user_avatar_removed',
                'context' => [
                    'groupId' => $tenant->id,
                ],
            ]);
        }
    }
}
