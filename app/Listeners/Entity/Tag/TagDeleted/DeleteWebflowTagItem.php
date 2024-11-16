<?php

namespace App\Listeners\Entity\Tag\TagDeleted;

use App\Events\Entity\Tag\TagDeleted;
use App\Models\Tenant;
use App\Models\Tenants\Integrations\Webflow;
use App\Models\Tenants\Tag;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class DeleteWebflowTagItem implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(TagDeleted $event): void
    {
        $tenant = Tenant::withoutEagerLoads()
            ->initialized()
            ->find($event->tenantId);

        if (! ($tenant instanceof Tenant)) {
            return;
        }

        $tenant->run(function (Tenant $tenant) use ($event) {
            $webflow = Webflow::retrieve();

            if (! $webflow->is_activated) {
                return;
            }

            $collection = $webflow->config->collections['tag'] ?? null;

            if (! is_array($collection)) {
                return; // @todo webflow - logging
            }

            $tag = Tag::onlyTrashed()
                ->withoutEagerLoads()
                ->find($event->tagId);

            if (! ($tag instanceof Tag)) {
                return;
            }

            if (! is_not_empty_string($tag->webflow_id)) {
                return; // @todo webflow - something went wrong
            }

            app('webflow')->item()->update(
                $collection['id'],
                $tag->webflow_id,
                [
                    'isArchived' => true,
                    'isDraft' => false,
                ],
                true,
            );
        });
    }
}
