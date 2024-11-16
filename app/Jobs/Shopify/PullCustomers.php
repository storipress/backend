<?php

namespace App\Jobs\Shopify;

use App\Models\Subscriber;
use App\Models\Tenant;
use App\Models\Tenants\Integration;
use App\Models\Tenants\Subscriber as TenantSubscriber;
use App\SDK\Shopify\Shopify;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Arr;
use Sentry\EventId;
use Sentry\State\Scope;
use Throwable;
use Webmozart\Assert\Assert;

use function Sentry\captureException;
use function Sentry\withScope;

class PullCustomers implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use Queueable;

    /**
     * The number of seconds after which the job's unique lock will be released.
     */
    public int $uniqueFor = 600;

    public function __construct(protected string $tenantKey) {}

    /**
     * The unique ID of the job.
     */
    public function uniqueId(): string
    {
        return $this->tenantKey.'_shopify_pull_customers';
    }

    public function failed(Throwable $exception): void
    {
        TenantSubscriber::enableSearchSyncing();

        $eventId = withScope(function (Scope $scope) use ($exception): ?EventId {
            $scope->setContext('debug', [
                'tenant' => $this->tenantKey,
            ]);

            return captureException($exception);
        });

        $template = file_get_contents(
            resource_path('notifications/slack/customer-data-pull-failure.json'),
        );

        Assert::stringNotEmpty($template);

        $mapping = [
            '{tenant}' => $this->tenantKey,
            '{sentry_url}' => sprintf('https://sentry.io/storipress/api/events/%s', $eventId),
        ];

        app('slack')->chatPostMessage([
            'channel' => config('services.slack.channel_id'),
            'blocks' => strtr($template, $mapping),
            'unfurl_links' => false,
        ]);
    }

    public function handle(Shopify $app): void
    {
        $tenant = Tenant::find($this->tenantKey);

        Assert::isInstanceOf($tenant, Tenant::class);

        tenancy()->initialize($tenant);

        TenantSubscriber::disableSearchSyncing();

        $integration = Integration::find('shopify');

        Assert::isInstanceOf($integration, Integration::class);

        $domain = Arr::get($integration->data, 'myshopify_domain');

        $token = Arr::get($integration->internals ?: [], 'access_token');

        Assert::stringNotEmpty($domain);

        Assert::stringNotEmpty($token);

        $app->setShop($domain);

        $app->setAccessToken($token);

        $customers = $app->getCustomers();

        $customers = array_filter($customers, fn ($customer) => $customer['email'] !== null);

        foreach ($customers as $customer) {
            /** @var Subscriber|null $subscriber */
            $subscriber = Subscriber::whereEmail($customer['email'])->first();

            if ($subscriber === null) {
                $this->signUpSubscriber($customer);

                continue;
            }

            $subscriber->update([
                'verified_at' => $subscriber->verified_at ?: now(),
                'first_name' => $customer['first_name'],
                'last_name' => $customer['last_name'],
            ]);

            /** @var Tenant $tenant */
            $tenant = tenant();

            $subscriber->tenants()->sync($tenant, false);

            /** @var TenantSubscriber|null $tenantSubscriber */
            $tenantSubscriber = TenantSubscriber::find($subscriber->getKey());

            if ($tenantSubscriber === null) {
                TenantSubscriber::create([
                    'id' => $subscriber->getKey(),
                    'shopify_id' => $customer['id'],
                    'signed_up_source' => 'shopify',
                    'newsletter' => $customer['accepts_marketing'],
                ]);

                continue;
            }

            $tenantSubscriber->update([
                'shopify_id' => $customer['id'],
                'newsletter' => $tenantSubscriber->newsletter ?: $customer['accepts_marketing'],
            ]);
        }

        TenantSubscriber::enableSearchSyncing();

        TenantSubscriber::makeAllSearchable(100);
    }

    /**
     * @param  array{id: string, email: string, accepts_marketing: bool, first_name: string, last_name: string}  $customer
     */
    protected function signUpSubscriber(array $customer): bool
    {
        $subscriber = Subscriber::create([
            'email' => $customer['email'],
            'verified_at' => now(),
            'first_name' => $customer['first_name'],
            'last_name' => $customer['last_name'],
        ]);

        $subscriber->tenants()->attach(tenant());

        $id = $subscriber->getKey();

        TenantSubscriber::create([
            'id' => $id,
            'shopify_id' => $customer['id'],
            'signed_up_source' => 'shopify',
            'newsletter' => $customer['accepts_marketing'],
        ]);

        return true;
    }
}
