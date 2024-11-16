<?php

namespace App\Listeners\Entity\Article\ArticleRestored;

use App\Events\Entity\Article\ArticleRestored;
use App\Jobs\Webflow\SyncArticleToWebflow;
use App\Models\Tenant;
use App\Models\Tenants\Article;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class DraftWebflowArticleItem implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(ArticleRestored $event): void
    {
        $tenant = Tenant::withoutEagerLoads()
            ->initialized()
            ->find($event->tenantId);

        if (!($tenant instanceof Tenant)) {
            return;
        }

        $tenant->run(function () use ($event) {
            $article = Article::withoutEagerLoads()
                ->find($event->articleId);

            if (!($article instanceof Article)) {
                return;
            }

            SyncArticleToWebflow::dispatch(
                $event->tenantId,
                $event->articleId,
            );
        });
    }
}
