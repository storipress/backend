<?php

namespace App\Listeners\Partners\WordPress\Webhooks\PostSaved;

use App\Events\Partners\WordPress\Webhooks\PostSaved;
use App\Jobs\WordPress\PullPostsFromWordPress;
use Illuminate\Contracts\Queue\ShouldQueue;

class PullPostFromWordPress implements ShouldQueue
{
    public function handle(PostSaved $event): void
    {
        PullPostsFromWordPress::dispatch($event->tenantId, $event->wordpressId);
    }
}
