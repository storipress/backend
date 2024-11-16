<?php

namespace App\Events\Partners\Postmark;

use Illuminate\Foundation\Events\Dispatchable;

class WebhookReceived
{
    use Dispatchable;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public int $eventId,
    ) {
        //
    }
}
