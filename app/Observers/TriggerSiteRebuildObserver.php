<?php

namespace App\Observers;

use App\Builder\ReleaseEventsBuilder;
use App\Enums\Article\PublishType;
use App\Models\Tenants\Article;
use Monooso\Unobserve\CanMute;

class TriggerSiteRebuildObserver
{
    use CanMute;

    /**
     * Handle the "updated" event.
     */
    public function updated(Article $model): void
    {
        if ($model->wasRecentlyCreated) {
            return;
        }

        if (! $model->wasChanged(['stage_id', 'published_at'])) {
            return;
        }

        $name = PublishType::immediate()->is($model->publish_type)
            ? 'article:publish'
            : 'article:update';

        (new ReleaseEventsBuilder())->handle($name, [
            'id' => $model->getKey(),
        ]);
    }
}
