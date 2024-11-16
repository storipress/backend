<?php

namespace App\Listeners\Partners\WordPress\Webhooks\CategoryCreated;

use App\Events\Partners\WordPress\Webhooks\CategoryCreated;
use App\Jobs\WordPress\PullCategoriesFromWordPress;
use Illuminate\Contracts\Queue\ShouldQueue;

class PullCategoryFromWordPress implements ShouldQueue
{
    public function handle(CategoryCreated $event): void
    {
        PullCategoriesFromWordPress::dispatch($event->tenantId, $event->wordpressId);
    }
}
