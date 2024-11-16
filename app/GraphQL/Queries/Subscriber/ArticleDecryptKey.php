<?php

namespace App\GraphQL\Queries\Subscriber;

use App\Enums\Article\Plan;
use App\Models\Tenant;
use App\Models\Tenants\Article;
use App\Models\Tenants\Subscriber;

class ArticleDecryptKey
{
    /**
     * @param  array{ id: string }  $args
     */
    public function __invoke($_, array $args): ?string
    {
        $tenant = tenant();

        if (!($tenant instanceof Tenant)) {
            return null;
        }

        $article = Article::find($args['id']);

        if ($article === null) {
            return null;
        }

        if (Plan::free()->is($article->plan)) {
            return $article->encryption_key;
        }

        $subscriber = Subscriber::find(
            auth()->id(),
        );

        if (!($subscriber instanceof Subscriber)) {
            return null;
        }

        if ($tenant->plan === 'free') {
            return $article->encryption_key;
        }

        if (!$tenant->subscription) {
            return $article->encryption_key;
        }

        if (Plan::member()->is($article->plan)) {
            return $article->encryption_key;
        }

        if (!$subscriber->subscribed) {
            return null;
        }

        return $article->encryption_key;
    }
}
