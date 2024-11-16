<?php

declare(strict_types=1);

namespace App\Listeners\Partners\Webflow\Onboarded;

use App\Events\Partners\Webflow\Onboarded;
use App\Jobs\Webflow\PullCategoriesFromWebflow;
use App\Jobs\Webflow\PullPostsFromWebflow;
use App\Jobs\Webflow\PullTagsFromWebflow;
use App\Jobs\Webflow\PullUsersFromWebflow;
use App\Jobs\Webflow\SyncArticleToWebflow;
use App\Jobs\Webflow\SyncDeskToWebflow;
use App\Jobs\Webflow\SyncTagToWebflow;
use App\Jobs\Webflow\SyncUserToWebflow;
use App\Models\Tenant;
use App\Notifications\Webflow\WebflowSyncFailedNotification;
use App\Notifications\Webflow\WebflowSyncFinishedNotification;
use App\Notifications\Webflow\WebflowSyncStartedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Throwable;

use function Sentry\captureException;

class SyncContent implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(Onboarded $event): void
    {
        $tenant = Tenant::initialized()
            ->withoutEagerLoads()
            ->with(['owner'])
            ->find($event->tenantId);

        if (!($tenant instanceof Tenant)) {
            return;
        }

        $key = sprintf('webflow-content-sync-%s', $tenant->id);

        if (!Cache::add($key, true, 300)) {
            return;
        }

        Bus::chain([
            function () use ($tenant) {
                $tenant->owner->notify(
                    new WebflowSyncStartedNotification(
                        $tenant->id,
                        $tenant->name,
                    ),
                );
            },
            new PullCategoriesFromWebflow($tenant->id),
            new PullTagsFromWebflow($tenant->id),
            new PullUsersFromWebflow($tenant->id),
            new PullPostsFromWebflow($tenant->id),
            new SyncTagToWebflow($tenant->id, null, true),
            new SyncDeskToWebflow($tenant->id, null, true),
            new SyncUserToWebflow($tenant->id, null, true),
            new SyncArticleToWebflow($tenant->id, null, true),
            function () use ($tenant) {
                $tenant->owner->notify(
                    new WebflowSyncFinishedNotification(
                        $tenant->id,
                        $tenant->name,
                    ),
                );
            },
            function () use ($key) {
                tenancy()->central(function () use ($key) {
                    Cache::delete($key);
                });
            },
        ])
            ->catch(function (Throwable $e) use ($tenant) {
                if ($e->getMessage() !== 'Failed to sync content to Webflow.') {
                    captureException($e);
                }

                $tenant->owner->notify(
                    new WebflowSyncFailedNotification(
                        $tenant->id,
                        $tenant->name,
                        [
                            'message' => $e->getMessage(),
                        ],
                    ),
                );
            })
            ->dispatch();
    }
}
