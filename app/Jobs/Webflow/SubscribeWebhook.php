<?php

declare(strict_types=1);

namespace App\Jobs\Webflow;

use App\Models\Tenant;
use App\Models\Tenants\Integrations\Webflow;
use Storipress\Webflow\Objects\Webhook;

/**
 * @phpstan-import-type TriggerType from Webhook
 */
class SubscribeWebhook extends WebflowJob
{
    /**
     * Create a new job instance.
     *
     * @param  TriggerType  $topic
     */
    public function __construct(
        public string $tenantId,
        public string $topic,
    ) {
        //
    }

    /**
     * {@inheritdoc}
     */
    public function rateLimitingKey(): string
    {
        return sprintf('%s:%s', $this->tenantId, $this->topic);
    }

    /**
     * {@inheritdoc}
     */
    public function overlappingKey(): string
    {
        return sprintf('%s:%s', $this->tenantId, $this->topic);
    }

    /**
     * {@inheritdoc}
     */
    public function throttlingKey(): string
    {
        return sprintf('webflow:%s:general', $this->tenantId);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $tenant = Tenant::withoutEagerLoads()
            ->initialized()
            ->find($this->tenantId);

        if (! ($tenant instanceof Tenant)) {
            return;
        }

        $tenant->run(function () {
            $webflow = Webflow::retrieve();

            $siteId = $webflow->config->site_id;

            if (! is_not_empty_string($siteId)) {
                return;
            }

            app('webflow')->webhook()->create(
                $siteId,
                $this->topic,
                route('webflow.events'),
            );
        });
    }
}
