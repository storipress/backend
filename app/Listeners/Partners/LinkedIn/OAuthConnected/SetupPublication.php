<?php

namespace App\Listeners\Partners\LinkedIn\OAuthConnected;

use App\Events\Partners\LinkedIn\OAuthConnected;
use App\Models\Tenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SetupPublication implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(OAuthConnected $event): void
    {
        $tenant = Tenant::where('id', $event->tenantId)->sole();

        $tenant->linkedin_data = [
            'id' => $event->user->id,
            'email' => $event->user->email,
        ];

        $tenant->save();
    }
}
