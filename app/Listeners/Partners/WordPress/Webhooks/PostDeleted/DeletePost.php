<?php

namespace App\Listeners\Partners\WordPress\Webhooks\PostDeleted;

use App\Events\Entity\Article\ArticleDeleted;
use App\Events\Partners\WordPress\Webhooks\PostDeleted;
use App\Models\Tenant;
use App\Models\Tenants\Article;
use App\Models\Tenants\UserActivity;
use Illuminate\Contracts\Queue\ShouldQueue;

class DeletePost implements ShouldQueue
{
    public function handle(PostDeleted $event): void
    {
        $tenant = Tenant::withoutEagerLoads()
            ->initialized()
            ->find($event->tenantId);

        if (!($tenant instanceof Tenant)) {
            return;
        }

        $tenant->run(function (Tenant $tenant) use ($event) {
            $article = Article::withTrashed()
                ->withoutEagerLoads()
                ->where('wordpress_id', $event->wordpressId)
                ->first();

            if (!($article instanceof Article)) {
                return;
            }

            $article->update([
                'wordpress_id' => null,
            ]);

            $article->delete();

            ArticleDeleted::dispatch(
                $tenant->id,
                $article->id,
            );

            UserActivity::log(
                name: 'wordpress.article.delete',
                subject: $article,
                data: [
                    'wordpress_id' => $event->wordpressId,
                ],
                userId: $tenant->owner->id,
            );
        });
    }
}
