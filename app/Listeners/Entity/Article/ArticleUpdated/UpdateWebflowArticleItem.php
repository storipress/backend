<?php

namespace App\Listeners\Entity\Article\ArticleUpdated;

use App\Events\Entity\Article\ArticleUpdated;
use App\Jobs\Webflow\SyncArticleToWebflow;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class UpdateWebflowArticleItem implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(ArticleUpdated $event): void
    {
        SyncArticleToWebflow::dispatch(
            $event->tenantId,
            $event->articleId,
        );
    }
}
