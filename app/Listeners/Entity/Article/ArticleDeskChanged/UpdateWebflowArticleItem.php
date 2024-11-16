<?php

namespace App\Listeners\Entity\Article\ArticleDeskChanged;

use App\Events\Entity\Article\ArticleDeskChanged;
use App\Jobs\Webflow\SyncArticleToWebflow;
use App\Jobs\Webflow\SyncDeskToWebflow;
use App\Models\Tenant;
use App\Models\Tenants\Article;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class UpdateWebflowArticleItem implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(ArticleDeskChanged $event): void
    {
        $tenant = Tenant::withoutEagerLoads()
            ->initialized()
            ->find($event->tenantId);

        if (!($tenant instanceof Tenant)) {
            return;
        }

        $tenant->run(function (Tenant $tenant) use ($event) {
            $article = Article::withoutEagerLoads()
                ->find($event->articleId);

            if (!($article instanceof Article)) {
                return;
            }

            SyncArticleToWebflow::dispatch(
                $event->tenantId,
                $article->id,
            );

            SyncDeskToWebflow::dispatch(
                $tenant->id,
                $event->originalDeskId,
            );

            SyncDeskToWebflow::dispatch(
                $tenant->id,
                $article->desk_id,
            );
        });
    }
}
