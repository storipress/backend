<?php

namespace App\Listeners\Entity\Article\ArticleUnpublished;

use App\Events\Entity\Article\ArticleUnpublished;
use App\Jobs\WordPress\SyncArticleToWordPress;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class UpdateWordpressPost implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(ArticleUnpublished $event): void
    {
        SyncArticleToWordPress::dispatch(
            $event->tenantId,
            $event->articleId,
        );
    }
}
