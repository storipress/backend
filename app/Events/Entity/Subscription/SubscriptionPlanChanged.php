<?php

namespace App\Events\Entity\Subscription;

use Illuminate\Foundation\Events\Dispatchable;

class SubscriptionPlanChanged
{
    use Dispatchable;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public int $userId,
        public string $current,
    ) {
        //
    }
}
