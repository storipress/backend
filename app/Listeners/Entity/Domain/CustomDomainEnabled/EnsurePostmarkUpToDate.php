<?php

namespace App\Listeners\Entity\Domain\CustomDomainEnabled;

use App\Events\Entity\Domain\CustomDomainEnabled;
use App\Models\Tenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class EnsurePostmarkUpToDate implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(CustomDomainEnabled $event): void
    {
        $tenant = Tenant::find($event->tenantId);

        if (! ($tenant instanceof Tenant)) {
            return;
        }

        if (empty($tenant->mail_domain) || empty($tenant->postmark_id)) {
            return;
        }

        $base = sprintf('https://api.postmarkapp.com/domains/%d', $tenant->postmark_id);

        $http = app('http')
            ->acceptJson()
            ->baseUrl($base)
            ->withHeaders([
                'X-Postmark-Account-Token' => config('services.postmark.account_token'),
            ]);

        $http->put('/verifyDkim');

        $http->put('/verifyReturnPath');
    }
}
