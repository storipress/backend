<?php

declare(strict_types=1);

namespace App\Listeners\Partners\Webflow\OAuthConnected;

use App\Events\Partners\Webflow\OAuthConnected;
use App\GraphQL\Mutations\Webflow\ConnectWebflow;
use App\Listeners\Traits\HasIngestHelper;
use App\Models\Tenant;
use App\Models\Tenants\Integrations\Webflow;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SetupIntegration implements ShouldQueue
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

        if (! ($tenant instanceof Tenant)) {
            return;
        }

        $tenant->run(function () use ($event) {
            Webflow::retrieve()->config->update([
                'v2' => true,
                'expired' => false,
                'access_token' => $event->user->token,
                'first_setup_done' => false,
                'user_id' => $event->user->id,
                'name' => $event->user->name,
                'email' => $event->user->email,
                'sync_when' => 'any',
                'scopes' => (new ConnectWebflow())->scopes(),
                'onboarding' => [
                    'site' => false,
                    'detection' => [
                        'site' => false,
                        'collection' => false,
                        'mapping' => [
                            'blog' => false,
                            'author' => false,
                            'desk' => false,
                            'tag' => false,
                        ],
                    ],
                    'collection' => [
                        'blog' => false,
                        'author' => false,
                        'desk' => false,
                        'tag' => false,
                    ],
                    'mapping' => [
                        'blog' => false,
                        'author' => false,
                        'desk' => false,
                        'tag' => false,
                    ],
                ],
            ]);
        });

        $this->ingest($event);
    }
}
