<?php

namespace App\Listeners\Entity\User\UserUpdated;

use App\Events\Entity\User\UserUpdated;
use App\Models\Tenants\Article;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Queue\InteractsWithQueue;

class TriggerScoutSync implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(UserUpdated $event): void
    {
        $user = User::withoutEagerLoads()
            ->with(['tenants'])
            ->find($event->userId);

        if (!($user instanceof User)) {
            return;
        }

        foreach ($user->tenants as $tenant) {
            if (!$tenant->initialized) {
                continue;
            }

            $tenant->run(function () use ($event) {
                Article::withoutEagerLoads()
                    ->select(['id'])
                    ->whereHas('authors', function (Builder $query) use ($event) {
                        $query->where('users.id', '=', $event->userId);
                    })
                    ->chunkById(50, function (Collection $articles) {
                        $articles->searchable();
                    });
            });
        }
    }
}
