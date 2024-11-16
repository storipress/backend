<?php

namespace App\Console\Migrations;

use App\Models\Tenant;
use App\Models\Tenants\Article;
use App\Models\Tenants\ArticleAutoPosting;
use Illuminate\Console\Command;

class MigrateImportedArticleMissingAuthors extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:imported-article-missing-authors';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        runForTenants(function (Tenant $tenant) {
            $owner = $tenant->owner;

            $posts = ArticleAutoPosting::with('article')
                ->whereIn('platform', ['shopify'])
                ->lazyById();

            foreach ($posts as $post) {
                $article = $post->article;

                if (!($article instanceof Article)) {
                    continue;
                }

                if ($article->authors()->count() > 0) {
                    continue;
                }

                $article->authors()->syncWithoutDetaching($owner);
            }
        });

        return static::SUCCESS;
    }
}
