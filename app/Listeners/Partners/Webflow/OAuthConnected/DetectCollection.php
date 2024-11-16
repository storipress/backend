<?php

declare(strict_types=1);

namespace App\Listeners\Partners\Webflow\OAuthConnected;

use App\Events\Partners\Webflow\CollectionConnected;
use App\Events\Partners\Webflow\OAuthConnected;
use App\Listeners\Traits\HasIngestHelper;
use App\Models\Tenant;
use App\Models\Tenants\Integrations\Webflow;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Str;
use Storipress\Webflow\Objects\SimpleCollection;

class DetectCollection implements ShouldQueue
{
    use HasIngestHelper;
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(OAuthConnected $event): void
    {
        $tenant = Tenant::withoutEagerLoads()
            ->initialized()
            ->find($event->tenantId);

        if (!($tenant instanceof Tenant)) {
            return;
        }

        if (!is_not_empty_string($event->user->token)) {
            return;
        }

        $sites = app('webflow')
            ->setToken($event->user->token)
            ->site()
            ->list();

        if (count($sites) !== 1) {
            return;
        }

        $tenant->run(function () {
            Webflow::retrieve()->config->update([
                'onboarding' => [
                    'detection' => [
                        'collection' => true,
                    ],
                ],
            ]);
        });

        $api = app('webflow')->collection();

        $collections = array_map(
            fn (SimpleCollection $item) => $api->get($item->id),
            $api->list($sites[0]->id),
        );

        $mapping = [
            'blog' => ['blog', 'article', 'post', 'content'],
            'author' => ['author', 'user', 'writer'],
            'desk' => ['categor', 'desk', 'topic'],
            'tag' => ['tag'],
        ];

        $data = [
            'onboarding' => [
                'detection' => [
                    'collection' => false,
                ],
            ],
            'raw_collections' => $collections,
        ];

        foreach ($collections as $collection) {
            foreach ($mapping as $key => $candidates) {
                if (!Str::contains($collection->slug, $candidates, true)) {
                    continue;
                }

                $data['collections'][$key] = $collection;
            }
        }

        $tenant->run(function () use ($data) {
            Webflow::retrieve()->config->update($data);
        });

        foreach (array_keys($data['collections'] ?? []) as $key) {
            CollectionConnected::dispatch(
                $event->tenantId,
                $key,
                $event->authId,
            );
        }

        $this->ingest($event);
    }
}
