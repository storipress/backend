<?php

namespace App\Observers;

use App\Enums\Release\State;
use App\Jobs\Slack\Notification;
use App\Models\Tenant;
use App\Models\Tenants\Article;
use App\Models\Tenants\Integration;
use App\Models\Tenants\Release;
use App\Models\Tenants\ReleaseEvent;
use App\Models\Tenants\UserActivity;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * @template TModel of \App\Models\Tenants\UserActivity|\App\Models\Tenants\Release
 */
class ArticleNoteNotifyingObserver
{
    /**
     * Handle the "updated" event.
     *
     * @param  TModel  $model
     */
    public function updated($model): void
    {
        if (! ($model instanceof Release)) {
            return;
        }

        $this->handle($model, 'published');
    }

    /**
     * Handle the "updated" event.
     *
     * @param  TModel  $model
     */
    public function created($model): void
    {
        if (! ($model instanceof UserActivity)) {
            return;
        }

        $this->handle($model, 'stage');
    }

    /**
     * @param  TModel  $model
     */
    protected function handle($model, string $type): void
    {
        $method = 'check'.Str::ucfirst($type).'Changed';

        $changed = method_exists($this, $method)
            ? $this->{$method}($model)
            : false;

        if (! $changed) {
            return;
        }

        /** @var Integration|null $slack */
        $slack = Integration::where('key', 'slack')
            ->activated()
            ->first();

        // Integration is not configured or disabled
        if ($slack === null || empty($slack->data)) {
            return;
        }

        /** @var array{published:string[], stage:string[]} $data */
        $data = $slack->data;

        if (empty($data[$type])) {
            return;
        }

        $method = $type.'Notify';

        if (! method_exists($this, $method)) {
            return;
        }

        $this->{$method}($model, $data[$type]);
    }

    protected function checkStageChanged(UserActivity $activity): bool
    {
        return $activity->wasRecentlyCreated && $activity->name === 'article.stage.change';
    }

    protected function checkPublishedChanged(Release $release): bool
    {
        return $release->wasChanged('state') && State::done()->is($release->state);
    }

    /**
     * @param  string[]  $channels
     */
    protected function stageNotify(UserActivity $activity, array $channels): void
    {
        /** @var array{new: int, old: int} $data */
        $data = $activity->data;

        /** @var int|null $stageId */
        $stageId = Arr::get($data, 'new');

        /** @var int $articleId */
        $articleId = $activity->subject_id;

        if (empty($articleId) || empty($stageId)) {
            return;
        }

        $userId = $activity->user_id;

        /** @var Tenant $tenant */
        $tenant = tenant();

        /** @var string $key */
        $key = $tenant->getKey();

        Notification::dispatch(
            $key,
            $articleId,
            'stage',
            [
                'user_id' => $userId,
                'channels' => $channels,
                'stage' => $stageId,
            ],
        );
    }

    /**
     * @param  string[]  $channels
     */
    protected function publishedNotify(Release $release, array $channels): void
    {
        $ids = ReleaseEvent::where('release_id', $release->id)
            ->whereIn('name', ['article:publish', 'article:schedule'])
            ->whereNotNull('data')
            ->pluck('data')
            ->flatten()
            ->unique()
            ->all();

        if (empty($ids)) {
            return;
        }

        $articles = Article::whereIn('id', $ids)->get();

        /** @var Tenant $tenant */
        $tenant = tenant();

        /** @var string $key */
        $key = $tenant->getKey();

        /** @var Article $article */
        foreach ($articles as $article) {
            if (! $article->published) {
                continue;
            }

            Notification::dispatch(
                $key,
                $article->id,
                'published',
                [
                    'user_id' => null,
                    'stage' => null,
                    'channels' => $channels,
                ],
            );
        }
    }
}
