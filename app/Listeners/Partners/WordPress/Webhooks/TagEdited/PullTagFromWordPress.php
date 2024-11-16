<?php

namespace App\Listeners\Partners\WordPress\Webhooks\TagEdited;

use App\Events\Partners\WordPress\Webhooks\TagEdited;
use App\Jobs\WordPress\PullTagsFromWordPress;
use Illuminate\Contracts\Queue\ShouldQueue;

class PullTagFromWordPress implements ShouldQueue
{
    public function handle(TagEdited $event): void
    {
        PullTagsFromWordPress::dispatch($event->tenantId, $event->wordpressId);
    }
}
