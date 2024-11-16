<?php

namespace App\Listeners\Entity\Article\ArticleDeleted;

use App\Events\Entity\Article\ArticleDeleted;
use App\Jobs\WordPress\SyncArticleToWordPress;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class DeleteWordPressPost implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(ArticleDeleted $event): void
    {
        SyncArticleToWordPress::dispatch(
            $event->tenantId,
            $event->articleId,
        );
    }
}
