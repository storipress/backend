<?php

namespace App\Listeners\Entity\Domain\CustomDomainEnabled;

use App\Events\Entity\Domain\CustomDomainEnabled;
use App\Models\Tenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Segment\Segment;
use Throwable;

use function Sentry\captureException;

class PushEventToRudderStack implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(CustomDomainEnabled $event): void
    {
        $tenant = Tenant::with('owner')->find($event->tenantId);

        if (! ($tenant instanceof Tenant)) {
            return;
        }

        try {
            Segment::track([
                'userId' => (string) $tenant->owner->id,
                'event' => 'tenant_custom_domain_enabled',
                'properties' => [
                    'tenant_uid' => $tenant->id,
                    'tenant_name' => $tenant->name,
                ],
                'context' => [
                    'groupId' => $tenant->id,
                ],
            ]);
        } catch (Throwable $e) {
            captureException($e);
        }
    }
}
