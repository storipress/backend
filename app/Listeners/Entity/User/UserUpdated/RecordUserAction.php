<?php

namespace App\Listeners\Entity\User\UserUpdated;

use App\Events\Entity\User\UserUpdated;
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
    public function handle(UserUpdated $event): void
    {
        $user = User::withoutEagerLoads()
            ->with(['tenants'])
            ->find($event->userId);

        if (!($user instanceof User)) {
            return;
        }

        foreach ($event->changes as $field => $data) {
            if ($field === 'updated_at') {
                continue;
            }

            foreach ($user->tenants as $tenant) {
                $event = sprintf('user_%s_updated', $field);

                Segment::track([
                    'userId' => (string) $user->id,
                    'event' => $event,
                    'properties' => [
                        $field => $data,
                    ],
                    'context' => [
                        'groupId' => $tenant->id,
                    ],
                ]);
            }
        }
    }
}
