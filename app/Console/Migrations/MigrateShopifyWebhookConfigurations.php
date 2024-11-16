<?php

namespace App\Console\Migrations;

use App\Models\Tenant;
use App\Models\Tenants\Integration;
use App\SDK\Shopify\Shopify;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Webmozart\Assert\Assert;

use function Sentry\captureException;

class MigrateShopifyWebhookConfigurations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:shopify:webhook-subscriptions {--cleanup}';

    /**
     * @var array<string, array<string>>
     */
    protected array $registers = [
        'app/uninstalled' => [
            'id',
        ],
        'customers/create' => [
            'id', 'email', 'first_name', 'last_name', 'accepts_marketing',
        ],
        'customers/update' => [
            'id', 'email', 'first_name', 'last_name', 'accepts_marketing',
        ],
        'customers/delete' => [
            'id',
        ],
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        runForTenants(function (Tenant $tenant) {
            $integration = Integration::where('key', 'shopify')
                ->whereNotNull('internals')
                ->first();

            if ($integration === null) {
                return;
            }

            $internals = $integration->internals ?: [];

            /** @var string|null $domain */
            $domain = Arr::get($internals, 'myshopify_domain');

            if (!$domain) {
                Log::debug('No myshopify_domain found for integration', ['tenant' => $tenant->id]);

                return;
            }

            /** @var string|null $token */
            $token = Arr::get($internals, 'access_token');

            if (!$token) {
                Log::debug('No access_token found for integration', ['tenant' => $tenant->id]);

                return;
            }

            $shopify = new Shopify($domain, $token);

            foreach ($this->registers as $topic => $fields) {
                $response = $shopify->registerWebhook($topic, $fields);

                if ($response['code'] === 401) {
                    Log::debug('shopify token is invalid.', ['tenant' => $tenant->id]);

                    if ($this->option('cleanup')) {
                        $integration->revoke();

                        $tenant->shopify_data = null;

                        $tenant->custom_site_template_path = null;

                        $tenant->custom_site_template = false;

                        $tenant->save();
                    }
                }

                if ($response['code'] >= 300) {
                    $message = json_encode($response);

                    Assert::stringNotEmpty($message);

                    if (!Str::contains($message, 'for this topic has already been taken', true)) {
                        captureException(new Exception($message));
                    }
                }

                return;
            }
        });

        return self::SUCCESS;
    }
}
