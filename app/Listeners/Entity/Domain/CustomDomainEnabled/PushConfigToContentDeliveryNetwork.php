<?php

namespace App\Listeners\Entity\Domain\CustomDomainEnabled;

use App\Console\Commands\Domain\PushConfigToContentDeliveryNetwork as PushConfigToContentDeliveryNetworkCommand;
use App\Events\Entity\Domain\CustomDomainEnabled;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Artisan;

class PushConfigToContentDeliveryNetwork implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(CustomDomainEnabled $event): void
    {
        Artisan::call(
            PushConfigToContentDeliveryNetworkCommand::class,
            [
                '--tenants' => [$event->tenantId],
            ],
        );
    }
}
