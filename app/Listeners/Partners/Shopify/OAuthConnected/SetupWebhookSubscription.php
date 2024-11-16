<?php

namespace App\Listeners\Partners\Shopify\OAuthConnected;

use App\Events\Partners\Shopify\OAuthConnected;
use App\SDK\Shopify\Shopify;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Str;
use Webmozart\Assert\Assert;

use function Sentry\captureException;

class SetupWebhookSubscription implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(protected Shopify $app) {}

    /**
     * Handle the event.
     */
    public function handle(OAuthConnected $event): void
    {
        $this->app->setShop($event->shop->myshopifyDomain);

        $this->app->setAccessToken($event->token);

        $registers = [
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
            'themes/publish' => [
                'id',
            ],
        ];

        $webhooks = $this->app->getWebhooks();

        $registers = array_filter($registers, fn ($topic) => ! in_array($topic, $webhooks), ARRAY_FILTER_USE_KEY);

        foreach ($registers as $topic => $fields) {
            $response = $this->app->registerWebhook($topic, $fields);

            if ($response['code'] >= 300) {
                $message = json_encode($response);

                Assert::stringNotEmpty($message);

                if (! Str::contains($message, 'for this topic has already been taken', true)) {
                    captureException(new Exception($message));
                }
            }
        }
    }
}
