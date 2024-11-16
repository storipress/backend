<?php

namespace App\Listeners\Partners\WordPress\Webhooks\UserEdited;

use App\Events\Partners\WordPress\Webhooks\UserEdited;
use App\Jobs\WordPress\PullUsersFromWordPress;
use Illuminate\Contracts\Queue\ShouldQueue;

class PullUserFromWordPress implements ShouldQueue
{
    public function handle(UserEdited $event): void
    {
        PullUsersFromWordPress::dispatch($event->tenantId, $event->wordpressId);
    }
}
