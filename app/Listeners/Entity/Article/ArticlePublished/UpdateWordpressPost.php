<?php

namespace App\Listeners\Entity\Article\ArticlePublished;

use App\Events\Entity\Article\ArticlePublished;
use App\Jobs\WordPress\SyncArticleToWordPress;
use App\Models\Tenant;
use App\Models\Tenants\Article;
use App\Models\Tenants\Integrations\WordPress;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class UpdateWordpressPost implements ShouldQueue
{
    use AutoPostHelper;
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(ArticlePublished $event): void
    {
        $tenant = Tenant::withoutEagerLoads()
            ->initialized()
            ->find($event->tenantId);

        if (!($tenant instanceof Tenant)) {
            return;
        }

        $tenant->run(function (Tenant $tenant) use ($event) {
            $wordpress = WordPress::retrieve();

            if (!$wordpress->is_activated) {
                return;
            }

            SyncArticleToWordPress::dispatch(
                $event->tenantId,
                $event->articleId,
            );

            $article = Article::withoutEagerLoads()
                ->published(true)
                ->find($event->articleId);

            if (!($article instanceof Article)) {
                return;
            }

            // run auto post
            $this->autoPost($tenant, $article, 90);
        });
    }
}
