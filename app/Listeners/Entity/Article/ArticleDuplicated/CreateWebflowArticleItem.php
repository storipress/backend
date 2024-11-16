<?php

namespace App\Listeners\Entity\Article\ArticleDuplicated;

use App\Events\Entity\Article\ArticleDuplicated;
use App\Jobs\Webflow\SyncArticleToWebflow;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class CreateWebflowArticleItem implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(ArticleDuplicated $event): void
    {
        SyncArticleToWebflow::dispatch(
            $event->tenantId,
            $event->articleId,
        );
    }
}
