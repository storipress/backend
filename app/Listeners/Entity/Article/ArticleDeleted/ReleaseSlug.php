<?php

namespace App\Listeners\Entity\Article\ArticleDeleted;

use App\Events\Entity\Article\ArticleDeleted;
use App\Models\Tenant;
use App\Models\Tenants\Article;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class ReleaseSlug implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(ArticleDeleted $event): void
    {
        $tenant = Tenant::find($event->tenantId);

        if (!($tenant instanceof Tenant)) {
            return;
        }

        $tenant->run(function () use ($event) {
            $article = Article::onlyTrashed()->find($event->articleId);

            if (!($article instanceof Article)) {
                return;
            }

            if (preg_match('/-\d{10}$/i', $article->slug) === 1) {
                return;
            }

            $article->update([
                'slug' => sprintf('%s-%d', $article->slug, now()->timestamp),
            ]);
        });
    }
}
