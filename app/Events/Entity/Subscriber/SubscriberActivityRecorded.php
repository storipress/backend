<?php

namespace App\Events\Entity\Subscriber;

use Illuminate\Foundation\Events\Dispatchable;

class SubscriberActivityRecorded
{
    use Dispatchable;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public string $tenantId,
        public int $subscriberId,
        public string $name,
    ) {
    }
}
