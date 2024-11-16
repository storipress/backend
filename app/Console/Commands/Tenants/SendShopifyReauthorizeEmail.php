<?php

namespace App\Console\Commands\Tenants;

use App\Enums\AccessToken\Type;
use App\Mail\Partners\Shopify\ReauthorizeMail as ShopifyReauthorizeMail;
use App\Models\AccessToken;
use App\Models\Tenant;
use App\Models\Tenants\Integration;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Mail;

class SendShopifyReauthorizeEmail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mail:shopify-reauthorize {--tenants=*}';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! empty($this->option('tenants'))) {
            $tenants = Tenant::initialized()
                ->whereIn('id', $this->option('tenants'))
                ->lazyById();
        }

        runForTenants(function (Tenant $tenant) {
            $integration = Integration::where('key', 'shopify')
                ->whereNotNull('internals')
                ->first();

            if (! $integration) {
                return;
            }

            $domain = Arr::get($tenant->shopify_data ?: [], 'myshopify_domain');

            if (! is_not_empty_string($domain)) {
                // unexpected error
                $this->error(sprintf('%s: No shopify domain found for tenant', $tenant->id));

                return;
            }

            $scopes = Arr::get($integration->internals ?: [], 'scopes');

            if (! is_array($scopes)) {
                // unexpected error
                $this->error(sprintf('%s: No scopes found for shopify integration', $tenant->id));

                return;
            }

            $expected = [
                'read_customers',
                'write_content',
                'write_themes',
            ];

            // ensure the user needs to reauthorize or not.
            if (empty(array_diff($expected, $scopes))) {
                return;
            }

            $user = $tenant->owner;

            $token = $user->accessTokens()->first()?->token;

            if (! is_not_empty_string($token)) {
                $token = $user->accessTokens()->create([
                    'name' => 'shopify-reauthorize',
                    'token' => AccessToken::token(Type::user()),
                    'abilities' => '*',
                    'ip' => '127.0.0.1',
                    'user_agent' => 'system-auto-generated',
                    'expires_at' => now()->addDays(7),
                ]);
            }

            $url = route('shopify.connect.reauthorize', [
                'api-token' => $token,
                'client_id' => $tenant->id,
            ]);

            Mail::to($user->email)->send(
                new ShopifyReauthorizeMail($user->first_name ?: 'there', $url),
            );
        }, $tenants ?? null);

        return self::SUCCESS;
    }
}
