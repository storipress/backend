<?php

namespace App\Listeners\Partners\WordPress\Webhooks\TagCreated;

use App\Events\Partners\WordPress\Webhooks\TagCreated;
use App\Jobs\WordPress\PullTagsFromWordPress;
use Illuminate\Contracts\Queue\ShouldQueue;

class PullTagFromWordPress implements ShouldQueue
{
    public function handle(TagCreated $event): void
    {
        PullTagsFromWordPress::dispatch($event->tenantId, $event->wordpressId);
    }
}
