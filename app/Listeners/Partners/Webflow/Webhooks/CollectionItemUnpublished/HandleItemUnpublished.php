<?php

declare(strict_types=1);

namespace App\Listeners\Partners\Webflow\Webhooks\CollectionItemUnpublished;

use App\Enums\Article\PublishType;
use App\Enums\Webflow\CollectionType;
use App\Events\Partners\Webflow\Webhooks\CollectionItemUnpublished;
use App\Models\Tenant;
use App\Models\Tenants\Article;
use App\Models\Tenants\Integrations\Webflow;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class HandleItemUnpublished implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(CollectionItemUnpublished $event): void
    {
        $tenant = Tenant::withoutEagerLoads()
            ->initialized()
            ->find($event->tenantId);

        if (!($tenant instanceof Tenant)) {
            return;
        }

        $tenant->run(function () use ($event) {
            $webflow = Webflow::retrieve();

            foreach ($webflow->config->collections as $type => $collection) {
                if ($collection['id'] !== $event->payload['collectionId']) {
                    continue;
                }

                if (CollectionType::blog()->isNot($type)) {
                    continue;
                }

                Article::where('webflow_id', '=', $event->payload['id'])->update([
                    'published_at' => null,
                    'publish_type' => PublishType::none(),
                ]);
            }
        });
    }
}
