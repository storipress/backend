<?php

namespace App\Observers;

use App\Jobs\RudderStack\SyncUserIdentify;
use App\Models\Tenant;
use App\Models\Tenants\Integration;
use App\Models\Tenants\User as TenantUser;
use App\Models\User;
use Laravel\Cashier\Subscription;
use Monooso\Unobserve\CanMute;

class RudderStackSyncingObserver
{
    use CanMute;

    /**
     * Handle the "created" event.
     */
    public function created(User|TenantUser|Tenant|Integration|Subscription $model): void
    {
        if ($model instanceof Subscription && $model->owner instanceof User) {
            SyncUserIdentify::dispatch((string) $model->owner->id);
        } elseif ($model instanceof User || $model instanceof TenantUser) {
            SyncUserIdentify::dispatch((string) $model->id);
        }
    }

    /**
     * Handle the "updated" event.
     */
    public function updated(User|TenantUser|Tenant|Integration|Subscription $model): void
    {
        if ($model->wasRecentlyCreated) {
            return;
        }

        if ((($tenant = $model) instanceof Tenant) || ($model instanceof Integration && ($tenant = tenant()) instanceof Tenant)) {
            foreach ($tenant->users->map->id as $id) {
                SyncUserIdentify::dispatch((string) $id);
            }
        } elseif ($model instanceof Subscription && $model->owner instanceof User) {
            SyncUserIdentify::dispatch((string) $model->owner->id);
        } elseif ($model instanceof User || $model instanceof TenantUser) {
            SyncUserIdentify::dispatch((string) $model->id);
        }
    }
}
