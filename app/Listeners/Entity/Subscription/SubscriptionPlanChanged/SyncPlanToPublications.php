<?php

namespace App\Listeners\Entity\Subscription\SubscriptionPlanChanged;

use App\Events\Entity\Subscription\SubscriptionPlanChanged;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SyncPlanToPublications implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(SubscriptionPlanChanged $event): void
    {
        $user = User::with(['publications'])->find($event->userId);

        if (!($user instanceof User)) {
            return;
        }

        foreach ($user->publications as $publication) {
            $publication->update([
                'plan' => $event->current,
            ]);
        }
    }
}
