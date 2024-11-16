<?php

declare(strict_types=1);

namespace App\Listeners\Entity\Integration\IntegrationConfigurationUpdated;

use App\Events\Entity\Integration\IntegrationConfigurationUpdated;
use App\Events\Partners\Webflow\Onboarded;
use App\Models\Tenant;
use App\Models\Tenants\Integrations\Webflow;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Arr;

class DetectWebflowOnboarded implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Determine whether the listener should be queued.
     */
    public function shouldQueue(IntegrationConfigurationUpdated $event): bool
    {
        return $event->integrationKey === 'webflow' &&
            isset($event->changes['onboarding']);
    }

    /**
     * Handle the event.
     */
    public function handle(IntegrationConfigurationUpdated $event): void
    {
        $tenant = Tenant::withoutEagerLoads()
            ->initialized()
            ->find($event->tenantId);

        if (!($tenant instanceof Tenant)) {
            return;
        }

        $tenant->run(function (Tenant $tenant) {
            $webflow = Webflow::retrieve();

            if (!$webflow->is_connected) {
                return;
            }

            $onboarding = Arr::except($webflow->config->onboarding, ['detection']);

            $onboarding = Arr::dot($onboarding);

            if (count(array_filter($onboarding)) !== count($onboarding)) {
                return;
            }

            Onboarded::dispatch($tenant->id);
        });
    }
}
