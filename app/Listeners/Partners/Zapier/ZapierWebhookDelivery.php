<?php

namespace App\Listeners\Partners\Zapier;

use App\Events\WebhookPushing;
use App\Listeners\Partners\WebhookDelivery;
use App\Models\Tenant;
use App\Models\Tenants\Article;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Sentry\State\Scope;
use Webmozart\Assert\Assert;

use function Sentry\captureException;
use function Sentry\withScope;

abstract class ZapierWebhookDelivery extends WebhookDelivery implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * @var string
     */
    protected $platform = 'zapier';

    /**
     * @var string
     */
    protected $topic;

    protected function remove(string $hook): bool
    {
        $response = app('http')->delete($hook);

        return $response->successful();
    }

    /**
     * Handle the event.
     *
     * @param  WebhookPushing<Article>  $event
     */
    public function handle(WebhookPushing $event): void
    {
        $tenant = Tenant::withTrashed()->find($event->tenantId);

        Assert::isInstanceOf($tenant, Tenant::class);

        if ($tenant->trashed()) {
            return;
        }

        $tenant->run(function () use ($event) {
            $webhooks = $this->get($this->platform, $this->topic);

            foreach ($webhooks as $webhook) {
                try {
                    $this->push($webhook, $event->model->toWebhookArray());
                } catch (Exception $e) {
                    withScope(function (Scope $scope) use ($event, $webhook, $e): void {
                        $scope->setContext('debug', [
                            'tenant' => $event->tenantId,
                            'model_type' => get_class($event->model),
                            'model_id' => $event->model->id,
                            'webhook_id' => $webhook->id,
                            'platform' => $webhook->platform,
                            'topic' => $webhook->topic,
                        ]);

                        captureException($e);
                    });
                }
            }
        });
    }
}
