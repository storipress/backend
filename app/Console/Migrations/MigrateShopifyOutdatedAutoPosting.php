<?php

namespace App\Console\Migrations;

use App\Enums\AutoPosting\State;
use App\Models\Tenant;
use App\Models\Tenants\Article;
use App\Models\Tenants\Integration;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Webmozart\Assert\Assert;

class MigrateShopifyOutdatedAutoPosting extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:shopify-outdated-auto-posting';

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

            $articles = Article::withTrashed()->whereNotNull('auto_posting')->lazyById();

            foreach ($articles as $article) {
                $autoPosting = $article->auto_posting;

                if (empty($autoPosting)) {
                    continue;
                }

                $articleId = Arr::get($autoPosting, 'shopify.article_id');

                if (empty($articleId)) {
                    continue;
                }

                $article->autoPostings()->updateOrCreate([
                    'target_id' => $articleId,
                ], [
                    'state' => State::posted(),
                    'platform' => 'shopify',
                    'domain' => $domain,
                    'prefix' => $prefix,
                    'pathname' => sprintf('/%s', $article->slug),
                ]);

                Arr::forget($autoPosting, 'shopify');

                $article->auto_posting = $autoPosting;

                $article->save();
            }
        });

        return static::SUCCESS;
    }
}
