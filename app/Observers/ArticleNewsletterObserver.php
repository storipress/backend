<?php

namespace App\Observers;

use App\Enums\Release\State;
use App\Jobs\Subscriber\SendArticleNewsletter;
use App\Models\Tenant;
use App\Models\Tenants\Article;
use App\Models\Tenants\Release;
use App\Models\Tenants\ReleaseEvent;
use Illuminate\Database\Eloquent\Collection;
use Webmozart\Assert\Assert;

class ArticleNewsletterObserver
{
    /**
     * Handle the "updated" event.
     */
    public function updated(Release $release): void
    {
        if (! $release->wasChanged('state')) {
            return;
        }

        if (! State::done()->is($release->state)) {
            return;
        }

        /** @var Tenant $tenant */
        $tenant = tenant();

        Assert::isInstanceOf($tenant, Tenant::class);

        // publication does not enable newsletter feature
        if (! $tenant->newsletter) {
            return;
        }

        $ids = ReleaseEvent::where('release_id', $release->id)
            ->whereIn('name', ['article:publish', 'article:schedule'])
            ->pluck('data')
            ->flatten() // creates a new array with all sub-array elements concatenated into it recursively
            ->unique() // ensure there won't be duplicate items
            ->filter() // remove the invalid items
            ->values() // reset the array keys
            ->toArray(); // convert Laravel collection to native PHP array

        if (empty($ids)) {
            return;
        }

        /** @var Collection<int, Article> $articles */
        $articles = Article::whereIn('id', $ids)->get();

        foreach ($articles as $article) {
            if (! $article->newsletter) {
                continue;
            }

            if ($article->newsletter_at !== null) {
                continue;
            }

            SendArticleNewsletter::dispatch(
                tenantId: $tenant->id,
                articleId: $article->id,
            );
        }
    }
}
