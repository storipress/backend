<?php

namespace App\Console\Migrations;

use App\Models\Tenants\Desk;
use App\Models\Tenants\Integration;
use App\SDK\Shopify\Shopify;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;

class MigrateShopifyDesksId extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:shopify-desks-id';

    public function __construct(protected readonly Shopify $app)
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

            // ensure has read_content scope
            $scopes = Arr::get($configuration, 'scopes');

            if (! is_array($scopes)) {
                return;
            }

            if (! in_array('read_content', $scopes) && ! in_array('write_content', $scopes)) {
                return;
            }

            $this->app->setShop($domain);

            $this->app->setAccessToken($token);

            $blogs = $this->app->getBlogs();

            $blogsHandle = [];

            foreach ($blogs as $blog) {
                $blogsHandle[$blog['handle']] = $blog['id'];
            }

            $desks = Desk::whereIn('slug', array_keys($blogsHandle))
                ->whereNull('shopify_id')
                ->lazyById();

            foreach ($desks as $desk) {
                $desk->shopify_id = $blogsHandle[$desk->slug];

                $desk->save();
            }
        });

        return self::SUCCESS;
    }
}
