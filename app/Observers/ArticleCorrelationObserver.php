<?php

namespace App\Observers;

use App\Console\Commands\Tenants\CalculateArticleCorrelation;
use App\Models\Tenant;
use App\Models\Tenants\Article;
use Illuminate\Support\Facades\Artisan;
use Monooso\Unobserve\CanMute;
use Webmozart\Assert\Assert;

class ArticleCorrelationObserver
{
    use CanMute;

    /**
     * Handle the "updated" event.
     */
    public function updated(Article $article): void
    {
        if (! $article->stage->ready) {
            return;
        }

        if (empty($article->plaintext)) {
            return;
        }

        $tenant = tenant();

        Assert::isInstanceOf($tenant, Tenant::class);

        Artisan::queue(
            CalculateArticleCorrelation::class,
            [
                'tenant' => $tenant->id,
                'article' => $article->id,
            ],
        );
    }
}
