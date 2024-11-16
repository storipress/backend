<?php

namespace App\Listeners\Entity\CustomField\CustomFieldValueCreated;

use App\Events\Entity\CustomField\CustomFieldValueCreated;
use App\Jobs\Webflow\SyncArticleToWebflow;
use App\Models\Tenant;
use App\Models\Tenants\CustomFieldValue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class UpdateWebflowArticleItem implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(CustomFieldValueCreated $event): void
    {
        $tenant = Tenant::withoutEagerLoads()
            ->initialized()
            ->find($event->tenantId);

        if (! ($tenant instanceof Tenant)) {
            return;
        }

        $tenant->run(function () use ($event) {
            $value = CustomFieldValue::withoutEagerLoads()
                ->find($event->valueId);

            if (! ($value instanceof CustomFieldValue)) {
                return;
            }

            SyncArticleToWebflow::dispatch(
                $event->tenantId,
                (int) $value->custom_field_morph_id,
            );
        });
    }
}
