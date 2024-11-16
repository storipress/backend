<?php

namespace App\Listeners\Entity\Article\ArticlePublished;

use App\Events\Entity\Article\ArticlePublished;
use App\Jobs\Webflow\SyncArticleToWebflow;
use App\Models\Tenant;
use App\Models\Tenants\Article;
use App\Models\Tenants\Integrations\Webflow;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class PublishWebflowArticleItem implements ShouldQueue
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

        if (! ($tenant instanceof Tenant)) {
            return;
        }

        $tenant->run(function (Tenant $tenant) use ($event) {
            $webflow = Webflow::retrieve();

            if (! $webflow->is_activated) {
                return;
            }

            $article = Article::withoutEagerLoads()
                ->published(true)
                ->find($event->articleId);

            if (! ($article instanceof Article)) {
                return;
            }

            SyncArticleToWebflow::dispatch(
                $event->tenantId,
                $event->articleId,
            );

            // run auto post
            $this->autoPost($tenant, $article); // @todo check here
        });
    }
}
