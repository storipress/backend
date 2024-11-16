<?php

namespace App\Listeners\Entity\Article\ArticleDuplicated;

use App\Events\Entity\Article\ArticleDuplicated;
use App\Jobs\WordPress\SyncArticleToWordPress;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class CreateWordpressPost implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(ArticleDuplicated $event): void
    {
        SyncArticleToWordPress::dispatch(
            $event->tenantId,
            $event->articleId,
        );
    }
}
