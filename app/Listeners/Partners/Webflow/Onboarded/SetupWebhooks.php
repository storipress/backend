<?php

declare(strict_types=1);

namespace App\Listeners\Partners\Webflow\Onboarded;

use App\Events\Partners\Webflow\Onboarded;
use App\Jobs\Webflow\SubscribeWebhook;
use App\Models\Tenant;
use App\Models\Tenants\Integrations\Webflow;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Storipress\Webflow\Objects\Webhook;

/**
 * @phpstan-import-type TriggerType from Webhook
 */
class SetupWebhooks implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * @var array<int, TriggerType>
     */
    protected array $topics = [
        'collection_item_created',
        'collection_item_changed',
        'collection_item_deleted',
        'collection_item_unpublished',
    ];

    public function handle(Onboarded $event): void
    {
        $tenant = Tenant::withoutEagerLoads()
            ->initialized()
            ->find($event->tenantId);

        if (! ($tenant instanceof Tenant)) {
            return;
        }

        $tenant->run(function (Tenant $tenant) {
            $webflow = Webflow::retrieve();

            $siteId = $webflow->config->site_id;

            if (! is_not_empty_string($siteId)) {
                return;
            }

            $webhooks = app('webflow')->webhook()->list($siteId);

            $triggerTypes = [];

            foreach ($webhooks as $webhook) {
                if ($webhook->url !== route('webflow.events')) {
                    continue;
                }

                $triggerTypes[] = $webhook->triggerType;
            }

            foreach (array_diff($this->topics, $triggerTypes) as $topic) {
                SubscribeWebhook::dispatch($tenant->id, $topic);
            }
        });
    }
}
