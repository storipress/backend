<?php

namespace App\Listeners\Entity\Article\ArticlePublished;

use App\Events\Entity\Article\ArticlePublished;
use App\Jobs\Webflow\SyncDeskToWebflow;
use App\Models\Tenant;
use App\Models\Tenants\Article;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class UpdateWebflowDeskItem implements ShouldQueue
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

        $tenant->run(function () use ($event) {
            $deskId = Article::withoutEagerLoads()
                ->find($event->articleId)
                ?->desk_id;

            if ($deskId === null) {
                return;
            }

            SyncDeskToWebflow::dispatch(
                $event->tenantId,
                $deskId,
            );
        });
    }
}
