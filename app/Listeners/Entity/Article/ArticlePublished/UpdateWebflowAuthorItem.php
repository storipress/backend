<?php

namespace App\Listeners\Entity\Article\ArticlePublished;

use App\Events\Entity\Article\ArticlePublished;
use App\Jobs\Webflow\SyncUserToWebflow;
use App\Models\Tenant;
use App\Models\Tenants\Article;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class UpdateWebflowAuthorItem implements ShouldQueue
{
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
            $article = Article::withoutEagerLoads()
                ->with([
                    'authors' => function (Builder $query) {
                        $query->withoutEagerLoads()
                            ->whereNotNull('webflow_id')
                            ->select(['id']);
                    },
                ])
                ->published(true)
                ->find($event->articleId);

            if (!($article instanceof Article)) {
                return;
            }

            foreach ($article->authors as $user) {
                SyncUserToWebflow::dispatch(
                    $tenant->id,
                    $user->id,
                );
            }
        });
    }
}
