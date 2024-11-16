<?php

declare(strict_types=1);

namespace App\Listeners\Partners\WordPress\Connected;

use App\Events\Partners\WordPress\Connected;
use App\Jobs\WordPress\PullAcfSchemaFromWordPress;
use App\Jobs\WordPress\PullCategoriesFromWordPress;
use App\Jobs\WordPress\PullPostsFromWordPress;
use App\Jobs\WordPress\PullTagsFromWordPress;
use App\Jobs\WordPress\PullUsersFromWordPress;
use App\Jobs\WordPress\SyncArticleToWordPress;
use App\Jobs\WordPress\SyncDeskToWordPress;
use App\Jobs\WordPress\SyncTagToWordPress;
use App\Jobs\WordPress\SyncUserToWordPress;
use App\Listeners\Traits\HasIngestHelper;
use App\Models\Tenant;
use App\Notifications\WordPress\WordPressSyncFailedNotification;
use App\Notifications\WordPress\WordPressSyncFinishedNotification;
use App\Notifications\WordPress\WordPressSyncStartedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Bus;
use Throwable;

use function Sentry\captureException;

class SyncContent implements ShouldQueue
{
    use HasIngestHelper;
    use InteractsWithQueue;

    /**
     * Determine whether the listener should be queued.
     */
    public function shouldQueue(Connected $event): bool
    {
        return version_compare($event->payload['version'], '0.0.14', '>=');
    }

    /**
     * Handle the event.
     */
    public function handle(Connected $event): void
    {
        $tenant = Tenant::initialized()
            ->withoutEagerLoads()
            ->with(['owner'])
            ->find($event->tenantId);

        if (! ($tenant instanceof Tenant)) {
            return;
        }

        Bus::chain([
            function () use ($tenant) {
                $tenant->owner->notify(
                    new WordPressSyncStartedNotification(
                        $tenant->id,
                        $tenant->name,
                    ),
                );
            },
            new PullAcfSchemaFromWordPress($tenant->id),
            new PullCategoriesFromWordPress($tenant->id),
            new PullTagsFromWordPress($tenant->id),
            new PullUsersFromWordPress($tenant->id),
            new PullPostsFromWordPress($tenant->id),
            new SyncTagToWordPress($tenant->id, null, true),
            new SyncDeskToWordPress($tenant->id, null, true),
            new SyncUserToWordPress($tenant->id, null, true),
            new SyncArticleToWordPress($tenant->id, null, true),
            function () use ($tenant) {
                $tenant->owner->notify(
                    new WordPressSyncFinishedNotification(
                        $tenant->id,
                        $tenant->name,
                    ),
                );
            },
        ])
            ->catch(function (Throwable $e) use ($tenant) {
                captureException($e);

                $tenant->owner->notify(
                    new WordPressSyncFailedNotification(
                        $tenant->id,
                        $tenant->name,
                    ),
                );
            })
            ->dispatch();

        $this->ingest($event);
    }
}
