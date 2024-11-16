<?php

declare(strict_types=1);

namespace App\Jobs\Webflow;

use App\Models\Tenant;
use App\Models\Tenants\Integrations\Webflow;

class PublishWebflowSite extends WebflowJob
{
    /**
     * {@inheritdoc}
     */
    public string $rateLimiterName = 'webflow-api-publish';

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $tenantId,
    ) {
        //
    }

    /**
     * {@inheritdoc}
     */
    public function rateLimitingKey(): string
    {
        return $this->tenantId;
    }

    /**
     * {@inheritdoc}
     */
    public function overlappingKey(): string
    {
        return $this->tenantId;
    }

    /**
     * {@inheritdoc}
     */
    public function throttlingKey(): string
    {
        return sprintf('webflow:%s:publish', $this->tenantId);
    }

    /**
     * Handle the given event.
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

            if (! $webflow->is_activated) {
                return;
            }

            if (! is_not_empty_string($webflow->config->site_id)) {
                return; // @todo webflow - something went wrong
            }

            $site = app('webflow')->site()->get($webflow->config->site_id);

            app('webflow')->site()->publish(
                $site->id,
                array_column($site->customDomains, 'id'),
                true,
            );

            ingest(
                data: [
                    'name' => 'webflow.site.publish',
                ],
                type: 'action',
            );
        });
    }
}
