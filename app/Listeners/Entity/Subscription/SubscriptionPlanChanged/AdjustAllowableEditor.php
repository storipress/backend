<?php

namespace App\Listeners\Entity\Subscription\SubscriptionPlanChanged;

use App\Events\Entity\Subscription\SubscriptionPlanChanged;
use App\Models\Tenant;
use App\Models\Tenants\User as TenantUser;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Str;
use Laravel\Cashier\Subscription;
use Webmozart\Assert\Assert;

class AdjustAllowableEditor implements ShouldQueue
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

        $quota = $this->quota($user, $event->current);

        $used = [$user->id]; // owner will always use 1 seat

        runForTenants(
            function (Tenant $tenant) use ($user, $quota, &$used) {
                $users = TenantUser::where('id', '!=', $user->id)
                    ->whereIn('role', ['admin', 'editor'])
                    ->get();

                foreach ($users as $tenantUser) {
                    // avoid double counting for the same user
                    if (in_array($tenantUser->id, $used, true)) {
                        continue;
                    }

                    if (count($used) < $quota) {
                        $used[] = $tenantUser->id;
                    } else {
                        $tenantUser->update(['role' => 'author']);

                        $tenant->users()->updateExistingPivot($tenantUser->id, ['role' => 'author']);
                    }
                }
            },
            $publications,
        );
    }

    /**
     * Get seats quota from user subscription.
     */
    protected function quota(User $user, string $plan): int
    {
        $key = sprintf('billing.quota.seats.%s', $plan);

        $quota = config($key);

        Assert::integer($quota);

        if (!in_array($plan, ['blogger', 'publisher'], true)) {
            return $quota;
        }

        $subscription = $user->subscription();

        if (!($subscription instanceof Subscription)) {
            return $quota;
        }

        $planId = $subscription->stripe_price;

        if ($planId === null) {
            return $quota;
        }

        if (Str::contains($planId, 'monthly', true)) {
            return $quota;
        }

        return $subscription->quantity ?: $quota;
    }
}
