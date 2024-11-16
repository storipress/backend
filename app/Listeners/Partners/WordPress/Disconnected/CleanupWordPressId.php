<?php

namespace App\Listeners\Partners\WordPress\Disconnected;

use App\Events\Partners\WordPress\Disconnected;
use App\Listeners\Traits\HasIngestHelper;
use App\Models\Tenant;
use App\Models\Tenants\Article;
use App\Models\Tenants\Desk;
use App\Models\Tenants\Tag;
use App\Models\Tenants\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class CleanupWordPressId implements ShouldQueue
{
    use HasIngestHelper;
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(Disconnected $event): void
    {
        $tenant = Tenant::withoutEagerLoads()
            ->initialized()
            ->find($event->tenantId);

        if (!($tenant instanceof Tenant)) {
            return;
        }

        $tenant->run(function () {
            Article::withTrashed()->whereNotNull('wordpress_id')->update(['wordpress_id' => null]);

            Desk::withTrashed()->whereNotNull('wordpress_id')->update(['wordpress_id' => null]);

            Tag::withTrashed()->whereNotNull('wordpress_id')->update(['wordpress_id' => null]);

            User::whereNotNull('wordpress_id')->update(['wordpress_id' => null]);
        });

        $this->ingest($event);
    }
}
