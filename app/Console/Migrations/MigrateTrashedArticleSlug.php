<?php

namespace App\Console\Migrations;

use App\Models\Tenants\Article;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MigrateTrashedArticleSlug extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:trashed-article-slug';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        runForTenants(function () {
            $articles = Article::withoutEagerLoads()
                ->onlyTrashed()
                ->lazyById();

            foreach ($articles as $article) {
                if ($article->deleted_at === null) {
                    continue;
                }

                if (preg_match('/-\d{10}$/i', $article->slug) === 1) {
                    continue;
                }

                $article->updateQuietly([
                    'slug' => sprintf(
                        '%s-%d',
                        Str::limit($article->slug, 240, ''),
                        $article->deleted_at->timestamp,
                    ),
                ]);
            }
        });

        return static::SUCCESS;
    }
}
