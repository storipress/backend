<?php

namespace App\Listeners\Partners\Shopify\OAuthConnected;

use App\Enums\AutoPosting\State;
use App\Events\Partners\Shopify\OAuthConnected;
use App\Models\Tenant;
use App\Models\Tenants\Article;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SetupArticleDistributions implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(OAuthConnected $event): void
    {
        $tenant = Tenant::findOrFail($event->tenantId);

        $tenant->run(function () use ($event) {
            $articles = Article::whereNotNull('published_at')->lazyById();

            foreach ($articles as $article) {
                if (! $article->published) {
                    continue;
                }

                $article->autoPostings()->updateOrCreate([
                    'platform' => 'shopify',
                ], [
                    'state' => State::posted(),
                    'domain' => $event->shop->domain,
                    'prefix' => '/a/blog',
                    'pathname' => sprintf('/posts/%s', $article->slug),
                ]);
            }
        });
    }
}
