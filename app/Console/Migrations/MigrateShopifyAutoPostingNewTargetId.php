<?php

namespace App\Console\Migrations;

use App\Models\Tenants\ArticleAutoPosting;
use App\Models\Tenants\Integration;
use App\SDK\Shopify\Shopify;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class MigrateShopifyAutoPostingNewTargetId extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:shopify-auto-posting-new-target-id';

    public function __construct(public Shopify $app)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        runForTenants(function () {
            $shopify = Integration::where('key', 'shopify')
                ->whereNotNull('internals')
                ->first();

            if (empty($shopify)) {
                return;
            }

            $configuration = $shopify->internals;

            if (empty($configuration)) {
                return;
            }

            $domain = Arr::get($configuration, 'myshopify_domain');

            if (! is_not_empty_string($domain)) {
                return;
            }

            $token = Arr::get($configuration, 'access_token');

            if (! is_not_empty_string($token)) {
                return;
            }

            $this->app->setShop($domain);

            $this->app->setAccessToken($token);

            $blogs = $this->app->getBlogs();

            $newTargetIds = [];

            foreach ($blogs as $blog) {
                $articles = $this->app->getArticles($blog['id']);

                foreach ($articles as $article) {
                    $newTargetIds[$article['id']] = sprintf('%s_%s', $blog['id'], $article['id']);
                }
            }

            $postings = ArticleAutoPosting::where('platform', 'shopify')
                ->whereNotNull('target_id')
                ->lazyById();

            foreach ($postings as $posting) {
                $targetId = $posting->target_id;

                if (is_not_empty_string($targetId) && Str::contains($targetId, '_')) {
                    continue;
                }

                $posting->target_id = $newTargetIds[intval($targetId)] ?? null;

                $posting->save();
            }
        });

        return self::SUCCESS;
    }
}
