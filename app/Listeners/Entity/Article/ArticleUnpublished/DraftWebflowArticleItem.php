<?php

namespace App\Listeners\Entity\Article\ArticleUnpublished;

use App\Events\Entity\Article\ArticleUnpublished;
use App\Jobs\Webflow\SyncArticleToWebflow;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class DraftWebflowArticleItem implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(ArticleUnpublished $event): void
    {
        SyncArticleToWebflow::dispatch(
            $event->tenantId,
            $event->articleId,
        );
    }
}
