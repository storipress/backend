<?php

namespace App\Listeners\Partners\WordPress\Webhooks\UserCreated;

use App\Events\Partners\WordPress\Webhooks\UserCreated;
use App\Jobs\WordPress\PullUsersFromWordPress;
use Illuminate\Contracts\Queue\ShouldQueue;

class PullUserFromWordPress implements ShouldQueue
{
    public function handle(UserCreated $event): void
    {
        PullUsersFromWordPress::dispatch($event->tenantId, $event->wordpressId);
    }
}
