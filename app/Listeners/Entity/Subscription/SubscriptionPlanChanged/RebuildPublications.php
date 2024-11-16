<?php

namespace App\Listeners\Entity\Subscription\SubscriptionPlanChanged;

use App\Builder\ReleaseEventsBuilder;
use App\Events\Entity\Subscription\SubscriptionPlanChanged;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Webmozart\Assert\Assert;

class RebuildPublications implements ShouldQueue
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

        $publications = $user
            ->publications
            ->where('initialized', '=', true)
            ->all();

        Assert::allIsInstanceOf($publications, Tenant::class);

        runForTenants(
            fn () => (new ReleaseEventsBuilder())->handle('subscription:change'),
            $publications,
        );
    }
}
