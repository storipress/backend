<?php

declare(strict_types=1);

namespace App\Listeners\Partners\Webflow\OAuthConnected;

use App\Events\Partners\Webflow\OAuthConnected;
use App\Listeners\Traits\HasIngestHelper;
use App\Models\Tenant;
use App\Models\Tenants\Integrations\Webflow;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class DetectMainSite implements ShouldQueue
{
    use HasIngestHelper;
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(OAuthConnected $event): void
    {
        $tenant = Tenant::withoutEagerLoads()
            ->initialized()
            ->find($event->tenantId);

        if (!($tenant instanceof Tenant)) {
            return;
        }

        if (!is_not_empty_string($event->user->token)) {
            return;
        }

        $tenant->run(function () {
            Webflow::retrieve()->config->update([
                'onboarding' => [
                    'detection' => [
                        'site' => true,
                    ],
                ],
            ]);
        });

        $sites = app('webflow')
            ->setToken($event->user->token)
            ->site()
            ->list();

        $data = [
            'onboarding' => [
                'detection' => [
                    'site' => false,
                ],
            ],
            'site_id' => null,
            'domain' => null,
            'raw_sites' => $sites,
        ];

        if (count($sites) === 1) {
            $data['site_id'] = $sites[0]->id;

            $domain = Arr::first(
                $sites[0]->customDomains,
                null,
                $sites[0]->defaultDomain,
            );

            if (is_string($domain)) {
                $data['domain'] = $domain;
            }
        }

        $tenant->run(function () use ($data) {
            Webflow::retrieve()->config->update($data);
        });

        if (isset($data['site_id'])) {
            DB::transaction(function () use ($tenant, $data) {
                $model = Tenant::withoutEagerLoads()
                    ->lockForUpdate()
                    ->find($tenant->id);

                if (!($model instanceof Tenant)) {
                    return;
                }

                $config = $model->webflow_data;

                $config['site_id'] = $data['site_id'];

                $model->update(['webflow_data' => $config]);
            });
        }

        $this->ingest($event);
    }
}
