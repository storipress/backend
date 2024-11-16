<?php

declare(strict_types=1);

namespace App\Console\Migrations;

use App\Models\Tenants\ArticleAutoPosting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateWebflowV2WebflowId extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:webflow-v2-webflow-id';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        runForTenants(function () {
            $posts = ArticleAutoPosting::withoutEagerLoads()
                ->where('platform', '=', 'webflow')
                ->whereNotNull('target_id')
                ->lazyById();

            foreach ($posts as $post) {
                DB::table('articles')
                    ->where('id', '=', $post->article_id)
                    ->update(['webflow_id' => $post->target_id]);
            }
        });

        return static::SUCCESS;
    }
}
