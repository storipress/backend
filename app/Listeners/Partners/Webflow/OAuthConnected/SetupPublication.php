<?php

declare(strict_types=1);

namespace App\Listeners\Partners\Webflow\OAuthConnected;

use App\Events\Partners\Webflow\OAuthConnected;
use App\Listeners\Traits\HasIngestHelper;
use App\Models\Tenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;

class SetupPublication implements ShouldQueue
{
    use HasIngestHelper;
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(OAuthConnected $event): void
    {
        DB::transaction(function () use ($event) {
            $tenant = Tenant::withoutEagerLoads()
                ->lockForUpdate()
                ->find($event->tenantId);

            if (! ($tenant instanceof Tenant)) {
                return;
            }

            $tenant->update([
                'webflow_data' => [
                    'id' => $event->user->id,
                    'email' => $event->user->email,
                    'access_token' => $event->user->token,
                ],
            ]);
        });

        $this->ingest($event);
    }
}
