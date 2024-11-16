<?php

namespace App\Listeners\Entity\Tenant\TenantDeleted;

use App\Console\Commands\Cloudflare\Pages\RemoveCloudflarePagesByTenant;
use App\Events\Entity\Tenant\TenantDeleted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Artisan;
use Throwable;

use function Sentry\captureException;

class CleanupCloudflarePage implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(TenantDeleted $event): void
    {
        try {
            Artisan::call(RemoveCloudflarePagesByTenant::class, [
                'tenant' => $event->tenantId,
            ]);
        } catch (Throwable $e) {
            captureException($e);
        }
    }
}
