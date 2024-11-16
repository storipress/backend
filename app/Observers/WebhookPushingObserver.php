<?php

namespace App\Observers;

use App\Enums\Release\State;
use App\Events\WebhookPushing;
use App\Models\Tenant;
use App\Models\Tenants\Article;
use App\Models\Tenants\Release;
use App\Models\Tenants\Subscriber;
use Illuminate\Support\Arr;
use Monooso\Unobserve\CanMute;
use Webmozart\Assert\Assert;

/**
 * @template TModel of Article|Release|Subscriber
 */
class WebhookPushingObserver
{
    use CanMute;

    /**
     * Handle the "created" event.
     *
     * @param  TModel  $model
     */
    public function created($model): void
    {
        $tenant = tenant();

        Assert::isInstanceOf($tenant, Tenant::class);

        if ($model instanceof Article) {
            $this->articleCreated($tenant->id, $model);
        }

        if ($model instanceof Subscriber) {
            $this->subscriberCreated($tenant->id, $model);
        }
    }

    protected function articleCreated(string $tenantId, Article $article): void
    {
        WebhookPushing::dispatch($tenantId, 'article.created', $article);
    }

    protected function subscriberCreated(string $tenantId, Subscriber $subscriber): void
    {
        WebhookPushing::dispatch($tenantId, 'subscriber.created', $subscriber);
    }

    /**
     * Handle the "updated" event.
     *
     * @param  TModel  $model
     */
    public function updated($model): void
    {
        $tenant = tenant();

        Assert::isInstanceOf($tenant, Tenant::class);

        if ($model instanceof Release) {
            $this->releaseUpdated($tenant->id, $model);
        }

        if ($model instanceof Article) {
            $this->articleUpdated($tenant->id, $model);
        }
    }

    protected function releaseUpdated(string $tenantId, Release $release): void
    {
        if (!$release->wasChanged('state')) {
            return;
        }

        if (!State::done()->is($release->state)) {
            return;
        }

        $events = $release->events()
            ->whereIn('name', ['article:publish', 'article:schedule'])
            ->get();

        if ($events->isEmpty()) {
            return;
        }

        $ids = [];

        foreach ($events as $event) {
            if ($event->data === null) {
                continue;
            }

            $ids = array_merge($ids, Arr::flatten($event->data));
        }

        $ids = array_unique($ids);

        $articles = Article::whereIn('id', $ids)->get();

        if ($articles->isEmpty()) {
            return;
        }

        foreach ($articles as $article) {
            WebhookPushing::dispatch($tenantId, 'article.published', $article);
        }
    }

    protected function articleUpdated(string $tenantId, Article $article): void
    {
        $push = false;

        if ($article->wasChanged(['stage_id'])) {
            WebhookPushing::dispatch($tenantId, 'article.stage.changed', $article);

            $push = true;
        }

        // unpublished
        if (!$article->published && $article->wasChanged(['stage_id', 'published_at'])) {
            /** @var array<mixed> $original */
            $original = $article->getOriginal();

            $originalArticle = new Article($original);

            if ($originalArticle->published) {
                WebhookPushing::dispatch($tenantId, 'article.unpublished', $article);

                $push = true;
            }
        }

        if ($push) {
            return;
        }

        if (!$article->published) {
            return;
        }

        WebhookPushing::dispatch($tenantId, 'article.updated', $article);
    }

    /**
     * Handle the "deleted" event.
     *
     * @param  TModel  $model
     */
    public function deleted($model): void
    {
        $tenant = tenant();

        Assert::isInstanceOf($tenant, Tenant::class);

        if (!$tenant->initialized) {
            return;
        }

        if ($model instanceof Article) {
            $this->articleDeleted($tenant->id, $model);
        }
    }

    protected function articleDeleted(string $tenantId, Article $article): void
    {
        WebhookPushing::dispatch($tenantId, 'article.deleted', $article);
    }
}
