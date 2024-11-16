<?php

namespace App\Observers;

use App\Jobs\Stripe\SyncCustomerDetails;
use App\Models\User;
use Monooso\Unobserve\CanMute;

class StripeCustomerSyncingObserver
{
    use CanMute;

    /**
     * Handle the "updated" event.
     */
    public function updated(User $user): void
    {
        if ($user->wasRecentlyCreated) {
            return;
        }

        if ($user->hasStripeId()) {
            return;
        }

        $monitoring = [
            'email', 'first_name', 'last_name',
            'phone_number', 'address',
        ];

        if (! $user->wasChanged($monitoring)) {
            return;
        }

        SyncCustomerDetails::dispatch($user->id);
    }
}
