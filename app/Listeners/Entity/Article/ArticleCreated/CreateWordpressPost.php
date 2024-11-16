<?php

namespace App\Listeners\Entity\Article\ArticleCreated;

use App\Events\Entity\Article\ArticleCreated;
use App\Jobs\WordPress\SyncArticleToWordPress;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class CreateWordpressPost implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(ArticleCreated $event): void
    {
        SyncArticleToWordPress::dispatch(
            $event->tenantId,
            $event->articleId,
        );
    }
}
