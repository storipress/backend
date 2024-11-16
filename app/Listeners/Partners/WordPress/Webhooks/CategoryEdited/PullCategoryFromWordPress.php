<?php

namespace App\Listeners\Partners\WordPress\Webhooks\CategoryEdited;

use App\Events\Partners\WordPress\Webhooks\CategoryEdited;
use App\Jobs\WordPress\PullCategoriesFromWordPress;
use Illuminate\Contracts\Queue\ShouldQueue;

class PullCategoryFromWordPress implements ShouldQueue
{
    public function handle(CategoryEdited $event): void
    {
        PullCategoriesFromWordPress::dispatch($event->tenantId, $event->wordpressId);
    }
}
