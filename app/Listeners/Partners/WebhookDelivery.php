<?php

namespace App\Listeners\Partners;

use App\Models\Tenants\Webhook;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Sentry\State\Scope;

use function Sentry\captureException;
use function Sentry\withScope;

abstract class WebhookDelivery
{
    /**
     * @param  array<mixed>  $data
     *
     * return int
     */
    protected function push(Webhook $webhook, array $data, ?string $uuid = null, string $method = 'post'): int
    {
        $uuid = $uuid ?: Str::uuid();

        $payload = [
            'event_uuid' => $uuid,
            'type' => $webhook->topic,
            'data' => $data,
            'created_at' => now()->timestamp,
        ];

        $response = $error = null;

        $url = $webhook->url;

        try {
            $response = app('http')->{$method}($url, $payload);

            $successful = $response->successful();
        } catch (Exception $e) {
            $successful = false;

            $error = [
                'code' => $e->getCode(),
                'message' => $e->getMessage(),
            ];

            withScope(function (Scope $scope) use ($webhook, $uuid, $e): void {
                $scope->setContext('debug', [
                    'tenant' => tenant('id'),
                    'webhook_id' => $webhook->id,
                    'platform' => $webhook->platform,
                    'topic' => $webhook->topic,
                    'event_uuid' => $uuid,
                ]);

                captureException($e);
            });
        }

        $this->save(
            webhook: $webhook,
            uuid: $uuid,
            successful: $successful,
            request: $payload,
            response: $response?->json(),
            error: $error,
        );

        return $successful;
    }

    /**
     * @param  array<mixed>  $request
     * @param  array<mixed>|null  $response
     * @param  array<mixed>|null  $error
     */
    protected function save(Webhook $webhook, string $uuid, bool $successful, array $request, ?array $response, ?array $error): void
    {
        $webhook->deliveries()->create([
            'event_uuid' => $uuid,
            'successful' => $successful,
            'request' => $request,
            'response' => $response,
            'error' => $error,
            'occurred_at' => $request['created_at'],
        ]);
    }

    /**
     * @return Collection<int, Webhook>
     */
    protected function get(string $platform, string $topic): Collection
    {
        return Webhook::where('platform', $platform)
            ->where('topic', $topic)
            ->get();
    }
}
