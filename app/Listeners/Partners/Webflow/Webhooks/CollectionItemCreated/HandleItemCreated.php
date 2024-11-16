<?php

declare(strict_types=1);

namespace App\Listeners\Partners\Webflow\Webhooks\CollectionItemCreated;

use App\Enums\Webflow\CollectionType;
use App\Events\Partners\Webflow\Webhooks\CollectionItemCreated;
use App\Jobs\Webflow\PullCategoriesFromWebflow;
use App\Jobs\Webflow\PullPostsFromWebflow;
use App\Jobs\Webflow\PullTagsFromWebflow;
use App\Jobs\Webflow\PullUsersFromWebflow;
use App\Models\Tenant;
use App\Models\Tenants\Integrations\Webflow;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class HandleItemCreated implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(CollectionItemCreated $event): void
    {
        $tenant = Tenant::withoutEagerLoads()
            ->initialized()
            ->find($event->tenantId);

        if (! ($tenant instanceof Tenant)) {
            return;
        }

        $tenant->run(function (Tenant $tenant) use ($event) {
            $webflow = Webflow::retrieve();

            foreach ($webflow->config->collections as $type => $collection) {
                if ($collection['id'] !== $event->payload['collectionId']) {
                    continue;
                }

                $job = match ($type) {
                    CollectionType::blog => PullPostsFromWebflow::class,
                    CollectionType::desk => PullCategoriesFromWebflow::class,
                    CollectionType::tag => PullTagsFromWebflow::class,
                    CollectionType::author => PullUsersFromWebflow::class,
                };

                dispatch(new $job($tenant->id, $event->payload['id']));
            }
        });
    }
}
