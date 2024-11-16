<?php

namespace App\Listeners;

use Illuminate\Support\Str;
use Stancl\Tenancy\Events\CreatingTenant;

final class GenerateTenantSecretKeys
{
    /**
     * Handle the event.
     *
     * @return void
     */
    public function handle(CreatingTenant $event)
    {
        $fields = ['wss_secret'];

        foreach ($fields as $field) {
            $event->tenant->setAttribute(
                $field,
                Str::random(64),
            );
        }
    }
}
