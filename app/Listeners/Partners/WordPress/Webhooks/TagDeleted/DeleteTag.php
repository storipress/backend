<?php

namespace App\Listeners\Partners\WordPress\Webhooks\TagDeleted;

use App\Events\Entity\Tag\TagDeleted as EntityTagDeleted;
use App\Events\Partners\WordPress\Webhooks\TagDeleted;
use App\Models\Tenant;
use App\Models\Tenants\Tag;
use App\Models\Tenants\UserActivity;
use Illuminate\Contracts\Queue\ShouldQueue;

class DeleteTag implements ShouldQueue
{
    public function handle(TagDeleted $event): void
    {
        $tenant = Tenant::withoutEagerLoads()
            ->initialized()
            ->find($event->tenantId);

        if (! ($tenant instanceof Tenant)) {
            return;
        }

        $tenant->run(function (Tenant $tenant) use ($event) {
            $tag = Tag::withoutEagerLoads()
                ->where('wordpress_id', $event->wordpressId)
                ->first();

            if (! ($tag instanceof Tag)) {
                return;
            }

            $tag->update([
                'wordpress_id' => null,
            ]);

            $tag->articles()->detach();

            $tag->delete();

            EntityTagDeleted::dispatch($tenant->id, $tag->id);

            UserActivity::log(
                name: 'wordpress.tag.delete',
                subject: $tag,
                data: [
                    'wordpress_id' => $event->wordpressId,
                ],
                userId: $tenant->owner->id,
            );
        });
    }
}
