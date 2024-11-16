<?php

namespace App\Http\Controllers\Partners\Shopify;

use App\Events\Partners\Shopify\WebhookReceived;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventsController extends ShopifyController
{
    /**
     * @var array<int, string>
     */
    protected array $topics = [
        'app/uninstalled',
        'customers/create',
        'customers/update',
        'customers/data_request',
        'customers/redact',
        'customers/delete',
        'themes/publish',
    ];

    public function __invoke(Request $request): JsonResponse
    {
        $error = response()->json('Unauthorized', 401);

        if (!$this->verifyWebhook($request)) {
            return $error;
        }

        $topic = $request->header('X-Shopify-Topic');

        $domain = $request->header('X-Shopify-Shop-Domain');

        if (!is_not_empty_string($topic) || !is_not_empty_string($domain)) {
            return $error;
        }

        // @see https://community.shopify.com/c/shopify-apis-and-sdks/does-the-header-quot-http-x-shopify-shop-domain-quot-response/td-p/119151
        $tenantIds = Tenant::whereJsonContains('data->shopify_data->myshopify_domain', $domain)
            ->where('initialized', '=', true)
            ->pluck('id');

        $payload = $request->all();

        $payload['domain'] = $domain;

        $payload['topic'] = $topic;

        if (!in_array($topic, $this->topics, true)) {
            $topic = 'unknown';
        }

        WebhookReceived::dispatch($topic, $payload, $tenantIds); // @phpstan-ignore-line

        return response()->json();
    }

    protected function verifyWebhook(Request $request): bool
    {
        $secret = config('services.shopify.client_secret');

        if (!is_string($secret) || empty($secret)) {
            return false;
        }

        $hmac = $request->header('X-Shopify-Hmac-Sha256');

        if (!is_string($hmac) || strlen($hmac) !== 44) {
            return false;
        }

        $data = $request->getContent();

        $known = base64_encode(
            hash_hmac('sha256', $data, $secret, true),
        );

        return hash_equals($known, $hmac);
    }
}
