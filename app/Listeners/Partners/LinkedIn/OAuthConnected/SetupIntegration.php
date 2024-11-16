<?php

namespace App\Listeners\Partners\LinkedIn\OAuthConnected;

use App\Events\Partners\LinkedIn\OAuthConnected;
use App\Jobs\Linkedin\SetupOrganizations;
use App\Models\Tenant;
use App\Models\Tenants\Integration;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SetupIntegration implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(OAuthConnected $event): void
    {
        $tenant = Tenant::where('id', $event->tenantId)->sole();

        $tenant->run(function (Tenant $tenant) use ($event) {
            $linkedin = Integration::where('key', 'linkedin')->sole();

            $linkedin->update([
                'data' => [
                    'id' => $event->user->id,
                    'name' => $event->user->name,
                    'email' => $event->user->email,
                    'thumbnail' => $event->user->avatar,
                    'authors' => [
                        [
                            'id' => 'urn:li:person:' . $event->user->id,
                            'name' => $event->user->name,
                            'thumbnail' => $event->user->avatar,
                        ],
                    ],
                    'setup_organizations' => false,
                ],
                'internals' => [
                    'id' => $event->user->id,
                    'name' => $event->user->name,
                    'email' => $event->user->email,
                    'thumbnail' => $event->user->avatar,
                    'authors' => [
                        [
                            'id' => 'urn:li:person:' . $event->user->id,
                            'name' => $event->user->name,
                            'thumbnail' => $event->user->avatar,
                        ],
                    ],
                    'access_token' => $event->token,
                    'refresh_token' => $event->refreshToken,
                    'scopes' => $event->scopes,
                    'setup_organizations' => false,
                ],
            ]);

            SetupOrganizations::dispatch($tenant->id)->delay(10);
        });
    }
}
