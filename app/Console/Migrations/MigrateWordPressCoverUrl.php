<?php

declare(strict_types=1);

namespace App\Console\Migrations;

use App\Jobs\WordPress\PullPostsFromWordPress;
use App\Models\Tenant;
use App\Models\Tenants\Article;
use App\Models\Tenants\Integrations\WordPress;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MigrateWordPressCoverUrl extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:wordpress-cover-url';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        runForTenants(function (Tenant $tenant) {
            $wordpress = WordPress::retrieve();

            if (!$wordpress->is_activated) {
                return;
            }

            $articles = Article::withoutEagerLoads()
                ->whereNotNull('wordpress_id')
                ->whereJsonContainsKey('cover->wordpress_id')
                ->select(['id', 'cover'])
                ->lazyById();

            foreach ($articles as $article) {
                $cover = $article->cover;

                if (empty($cover)) {
                    continue;
                }

                if (!is_not_empty_string($cover['url'])) {
                    continue;
                }

                if (Str::contains($cover['url'], 'assets.stori.press')) {
                    continue;
                }

                PullPostsFromWordPress::dispatchSync(
                    $tenant->id,
                    $article->wordpress_id,
                );
            }
        });

        return static::SUCCESS;
    }
}
