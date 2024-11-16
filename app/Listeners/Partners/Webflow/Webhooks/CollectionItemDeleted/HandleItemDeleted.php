<?php

declare(strict_types=1);

namespace App\Listeners\Partners\Webflow\Webhooks\CollectionItemDeleted;

use App\Enums\Webflow\CollectionType;
use App\Events\Partners\Webflow\Webhooks\CollectionItemDeleted;
use App\Models\Tenant;
use App\Models\Tenants\Article;
use App\Models\Tenants\Desk;
use App\Models\Tenants\Integrations\Webflow;
use App\Models\Tenants\Tag;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class HandleItemDeleted implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(CollectionItemDeleted $event): void
    {
        $tenant = Tenant::withoutEagerLoads()
            ->initialized()
            ->find($event->tenantId);

        if (! ($tenant instanceof Tenant)) {
            return;
        }

        $tenant->run(function () use ($event) {
            $webflow = Webflow::retrieve();

            foreach ($webflow->config->collections as $type => $collection) {
                if ($collection['id'] !== $event->payload['collectionId']) {
                    continue;
                }

                $model = match ($type) {
                    CollectionType::blog => Article::class,
                    CollectionType::desk => Desk::class,
                    CollectionType::tag => Tag::class,
                    default => null,
                };

                if ($model === null) {
                    break;
                }

                $model::where('webflow_id', '=', $event->payload['id'])->delete();
            }
        });
    }
}
