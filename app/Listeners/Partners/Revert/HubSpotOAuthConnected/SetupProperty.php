<?php

namespace App\Listeners\Partners\Revert\HubSpotOAuthConnected;

use App\Events\Partners\Revert\HubSpotOAuthConnected;
use App\Jobs\Revert\SetupHubSpotProperty;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SetupProperty implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(HubSpotOAuthConnected $event): void
    {
        SetupHubSpotProperty::dispatch($event->tenantId);
    }
}
