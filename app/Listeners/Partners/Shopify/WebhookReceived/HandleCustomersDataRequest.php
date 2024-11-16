<?php

namespace App\Listeners\Partners\Shopify\WebhookReceived;

use App\Events\Partners\Shopify\WebhookReceived;
use App\Models\Subscriber;
use App\Models\Tenants\Integration;
use App\Models\Tenants\Subscriber as TenantSubscriber;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use League\Csv\CannotInsertRecord;
use League\Csv\Writer;
use Sentry\State\Scope;
use Webmozart\Assert\Assert;

use function Sentry\configureScope;
use function strtr;

class HandleCustomersDataRequest implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Determine whether the listener should be queued.
     */
    public function shouldQueue(WebhookReceived $event): bool
    {
        return $event->topic === 'customers/data_request';
    }

    /**
     * Handle the event.
     *
     * @throws CannotInsertRecord
     *
     * @see https://shopify.dev/docs/apps/webhooks/configuration/mandatory-webhooks#customers-data_request-payload
     */
    public function handle(WebhookReceived $event): void
    {
        configureScope(function (Scope $scope) use ($event): void {
            $scope->setContext('payload', $event->payload);

            $scope->setContext('tenants', $event->tenantIds->toArray());
        });

        $found = false;

        $email = Arr::get($event->payload, 'customer.email');

        if (! is_not_empty_string($email)) {
            return;
        }

        $subscriber = Subscriber::where('email', '=', $email)->first();

        if ($subscriber === null) {
            return;
        }

        $subscriberId = $subscriber->id;

        $payload = [];

        tenancy()->runForMultiple(
            $event->tenantIds,
            function () use (&$found, &$payload, $subscriberId) {
                if ($found) {
                    return;
                }

                $shopify = Integration::find('shopify');

                if ($shopify === null) {
                    return;
                }

                $ownerEmail = $shopify->internals['email'] ?? '';

                if (! is_not_empty_string($ownerEmail)) {
                    return;
                }

                $subscriber = TenantSubscriber::find($subscriberId);

                if ($subscriber === null) {
                    return;
                }

                $group = unique_token();

                $payload['owner_email'] = $ownerEmail;

                $payload['base_info_url'] = $this->exportProfile($group, $subscriber);

                $payload['activities_url'] = $this->exportActivities($group, $subscriber);

                $found = true;
            },
        );

        if (! isset($payload['owner_email'])) {
            return;
        }

        $template = file_get_contents(
            resource_path('notifications/slack/shopify-data-requests.json'),
        );

        Assert::stringNotEmpty($template);

        $mapping = [
            '{shop_email}' => $payload['owner_email'],
            '{shop_url}' => $event->payload['domain'],
            '{subscriber_email}' => $email,
            '{env}' => app()->environment(),
            '{base_info_url}' => $payload['base_info_url'],
            '{activities_url}' => $payload['activities_url'],
        ];

        app('slack')->chatPostMessage([
            'channel' => config('services.slack.channel_id'),
            'blocks' => strtr($template, $mapping),
            'unfurl_links' => false,
        ]);
    }

    /**
     * @throws CannotInsertRecord
     */
    protected function exportProfile(string $group, TenantSubscriber $subscriber): string
    {
        $path = temp_file();

        $csv = Writer::createFromPath($path, 'w');

        $csv->insertOne(['Email', 'First Name', 'Last Name']);

        $csv->insertOne([
            $subscriber->email,
            $subscriber->first_name,
            $subscriber->last_name,
        ]);

        return $this->toCloud($group, $path, 'profile.csv');
    }

    /**
     * @throws CannotInsertRecord
     */
    protected function exportActivities(string $group, TenantSubscriber $subscriber): string
    {
        $path = temp_file();

        $csv = Writer::createFromPath($path, 'w');

        $csv->insertOne(['Name', 'Occurred At', 'Data']);

        foreach ($subscriber->events()->lazy() as $event) {
            $csv->insertOne([
                $event->name,
                $event->occurred_at->toDateTimeString(),
                json_encode($event->data) ?: null,
            ]);
        }

        return $this->toCloud($group, $path, 'activities.csv');
    }

    protected function toCloud(string $group, string $source, string $filename): string
    {
        $path = sprintf('assets/takeouts/%s/%s', $group, $filename);

        $fp = fopen($source, 'r');

        Assert::resource($fp);

        $cloud = Storage::cloud();

        $cloud->put($path, $fp);

        return $cloud->temporaryUrl($path, now()->addDays(7));
    }
}
