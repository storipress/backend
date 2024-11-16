<?php

namespace App\Listeners\Entity\Article\ArticleCreated;

use App\Events\Entity\Article\ArticleCreated;
use App\Jobs\Webflow\SyncArticleToWebflow;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class CreateWebflowArticleItem implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(ArticleCreated $event): void
    {
        SyncArticleToWebflow::dispatch(
            $event->tenantId,
            $event->articleId,
        );
    }
}
