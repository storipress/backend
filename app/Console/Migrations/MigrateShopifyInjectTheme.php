<?php

namespace App\Console\Migrations;

use App\Events\Partners\Shopify\ThemeTemplateInjecting;
use App\Listeners\Partners\Shopify\HandleThemeTemplateInjection;
use App\Models\Tenant;
use App\Models\Tenants\Integration;
use App\SDK\Shopify\Shopify;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\LazyCollection;

class MigrateShopifyInjectTheme extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:shopify-inject-theme';

    public function __construct(protected readonly Shopify $app)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        /** @var LazyCollection<int, Tenant> $tenants */
        $tenants = Tenant::initialized()->lazyById();

        $app = new Shopify();

        foreach ($tenants as $tenant) {
            if ($tenant->shopify_data === null) {
                continue;
            }

            $valid = $tenant->run(function () {
                $shopify = Integration::where('key', 'shopify')
                    ->first();

                if ($shopify === null) {
                    return false;
                }

                $internals = $shopify->internals;

                if (empty($internals)) {
                    return false;
                }

                // ensure has write_themes scope
                $scopes = Arr::get($internals, 'scopes', []);

                if (!is_array($scopes)) {
                    return false;
                }

                // have not reauthorize
                if (!in_array('write_themes', $scopes)) {
                    return false;
                }

                return true;
            });

            if (!$valid) {
                continue;
            }

            $event = new ThemeTemplateInjecting($tenant->id);

            $listener = new HandleThemeTemplateInjection($app);

            $listener->handle($event);
        }

        return self::SUCCESS;
    }
}
