<?php

namespace App\Listeners\Entity\Subscription\SubscriptionPlanChanged;

use App\Events\Entity\Subscription\SubscriptionPlanChanged;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Webmozart\Assert\Assert;

class AdjustAllowablePublication implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(SubscriptionPlanChanged $event): void
    {
        $user = User::with(['publications'])->find($event->userId);

        if (! ($user instanceof User)) {
            return;
        }

        $key = sprintf('billing.quota.publications.%s', $event->current);

        $quota = config($key);

        Assert::integer($quota);

        foreach ($user->publications as $idx => $publication) {
            $publication->update([
                'enabled' => $idx < $quota,
            ]);
        }
    }
}
