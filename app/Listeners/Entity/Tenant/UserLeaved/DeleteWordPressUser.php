<?php

namespace App\Listeners\Entity\Tenant\UserLeaved;

use App\Events\Entity\Tenant\UserLeaved;
use App\Models\Tenant;
use App\Models\Tenants\Integrations\WordPress;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class DeleteWordPressUser implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(UserLeaved $event): void
    {
        $tenant = Tenant::withoutEagerLoads()
            ->initialized()
            ->find($event->tenantId);

        if (!($tenant instanceof Tenant)) {
            return;
        }

        $wordpressId = $event->data['wordpress_id'];

        if (!is_int($wordpressId)) {
            return;
        }

        $tenant->run(function () use ($wordpressId) {
            $wordpress = WordPress::retrieve();

            if (!$wordpress->is_activated) {
                return;
            }

            $userId = $wordpress->config->user_id;

            app('wordpress')->user()->delete($wordpressId, $userId);
        });
    }
}
