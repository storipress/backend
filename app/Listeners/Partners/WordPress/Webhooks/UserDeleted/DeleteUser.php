<?php

namespace App\Listeners\Partners\WordPress\Webhooks\UserDeleted;

use App\Events\Partners\WordPress\Webhooks\UserDeleted;
use App\Models\Tenant;
use App\Models\Tenants\Article;
use App\Models\Tenants\User;
use App\Models\Tenants\UserActivity;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;

class DeleteUser implements ShouldQueue
{
    public function handle(UserDeleted $event): void
    {
        $tenant = Tenant::withoutEagerLoads()
            ->initialized()
            ->find($event->tenantId);

        if (!($tenant instanceof Tenant)) {
            return;
        }

        $tenant->run(function (Tenant $tenant) use ($event) {
            $user = User::withoutEagerLoads()
                ->with('desks')
                ->where('wordpress_id', $event->wordpressId)
                ->first();

            if (!($user instanceof User)) {
                return;
            }

            $user->update([
                'wordpress_id' => null,
            ]);

            $reassign = $event->payload['reassign'];

            $assignUser = User::withoutEagerLoads()
                ->where('wordpress_id', $reassign)
                ->first();

            if (!($assignUser instanceof User)) {
                $assignUser = User::withoutEagerLoads()
                    ->where('id', $tenant->owner->id)
                    ->first();

                if (!($assignUser instanceof User)) {
                    return;
                }
            }

            DB::transaction(function () use ($user, $assignUser) {
                $query = $user->articles();

                /** @var Article $article */
                foreach ($query->lazyById() as $article) {
                    $article->authors()->syncWithoutDetaching($assignUser);

                    $article->refresh();

                    $article->searchable();
                }

                $assignUser->desks()->syncWithoutDetaching($user->desks);
            });

            UserActivity::log(
                name: 'wordpress.team.leave',
                subject: $user,
                data: [
                    'wordpress_id' => $event->wordpressId,
                    'reassign' => $event->payload['reassign'],
                ],
                userId: $tenant->owner->id,
            );
        });
    }
}
