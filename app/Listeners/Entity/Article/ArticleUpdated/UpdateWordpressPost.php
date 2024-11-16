<?php

namespace App\Listeners\Entity\Article\ArticleUpdated;

use App\Events\Entity\Article\ArticleUpdated;
use App\Jobs\WordPress\SyncArticleToWordPress;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class UpdateWordpressPost implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(ArticleUpdated $event): void
    {
        SyncArticleToWordPress::dispatch(
            $event->tenantId,
            $event->articleId,
        );
    }
}
