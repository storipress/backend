<?php

namespace App\Console\Migrations;

use App\Enums\AutoPosting\State;
use App\Models\Tenant;
use App\Models\Tenants\Article;
use App\Models\Tenants\Integration;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Webmozart\Assert\Assert;

class MigrateShopifyArticleDistributions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:shopify-article-distributions';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        runForTenants(function (Tenant $tenant) {
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

            $articles = Article::whereNotNull('published_at')->lazyById();

            foreach ($articles as $article) {
                if (!$article->published) {
                    continue;
                }

                $article->autoPostings()->updateOrCreate([
                    'platform' => 'shopify',
                ], [
                    'state' => State::posted(),
                    'domain' => $domain,
                    'prefix' => $prefix,
                    'pathname' => sprintf('/%s', $article->slug),
                ]);
            }
        });

        return static::SUCCESS;
    }
}
