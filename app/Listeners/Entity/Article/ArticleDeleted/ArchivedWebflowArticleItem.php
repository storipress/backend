<?php

namespace App\Listeners\Entity\Article\ArticleDeleted;

use App\Events\Entity\Article\ArticleDeleted;
use App\Jobs\Webflow\SyncArticleToWebflow;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class ArchivedWebflowArticleItem implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(ArticleDeleted $event): void
    {
        SyncArticleToWebflow::dispatch(
            $event->tenantId,
            $event->articleId,
        );
    }
}
