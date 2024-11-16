<?php

namespace App\Listeners\Entity\Article\ArticlePublished;

use App\Enums\AutoPosting\State;
use App\Events\Entity\Article\ArticlePublished;
use App\Events\Entity\Article\AutoPostingPathUpdated;
use App\Models\Tenant;
use App\Models\Tenants\Article;
use App\Models\Tenants\Integration;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Arr;
use Webmozart\Assert\Assert;

class UpdateShopifyArticleDistribution implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Determine whether the listener should be queued.
     */
    public function shouldQueue(ArticlePublished $event): bool
    {
        return Integration::isShopifyActivate();
    }

    /**
     * Handle the event.
     */
    public function handle(ArticlePublished $event): void
    {
        $tenant = Tenant::findOrFail($event->tenantId);

        $tenant->run(function (Tenant $tenant) use ($event) {
            $integration = Integration::where('key', 'shopify')->first();

            if (empty($integration)) {
                return;
            }

            $data = $integration->data;

            $configuration = $integration->internals ?: [];

            // If the configuration is empty, we can skip this tenant.
            if (empty($configuration)) {
                return;
            }

            $prefix = Arr::get($data, 'prefix', Arr::get($configuration, 'prefix', '/a/blog'));

            Assert::notNull($prefix);

            $domain = Arr::get($configuration, 'domain');

            Assert::notNull($domain);

            $article = Article::where('id', $event->articleId)->sole();

            $article->autoPostings()->updateOrCreate([
                'platform' => 'shopify',
            ], [
                'state' => State::posted(),
                'domain' => $domain,
                'prefix' => $prefix,
                'pathname' => sprintf('/posts/%s', $article->slug),
            ]);

            AutoPostingPathUpdated::dispatch('shopify', $tenant->id, $article->id);
        });
    }
}
