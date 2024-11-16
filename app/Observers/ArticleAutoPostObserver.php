<?php

namespace App\Observers;

use App\Enums\AutoPosting\State as AutoPostingState;
use App\Enums\Release\State;
use App\Jobs\Integration\AutoPost;
use App\Jobs\Integration\AutoPost2;
use App\Models\Tenant;
use App\Models\Tenants\Article;
use App\Models\Tenants\ArticleAutoPosting;
use App\Models\Tenants\Integration;
use App\Models\Tenants\Release;
use App\Models\Tenants\ReleaseEvent;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

class ArticleAutoPostObserver
{
    /**
     * Handle the "updated" event.
     */
    public function updated(Release $release): void
    {
        if (! $release->wasChanged('state')) {
            return;
        }

        if (! State::done()->is($release->state)) {
            return;
        }

        $tenant = tenant();

        if (! ($tenant instanceof Tenant)) {
            return;
        }

        $events = ReleaseEvent::where('release_id', $release->id)
            ->whereIn('name', ['article:publish', 'article:schedule', 'article:build'])
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

        $platforms = $this->getActivatedIntegration();

        $platforms = array_intersect($platforms, ['facebook', 'twitter']);

        foreach ($articles as $article) {
            if (! $article->published) {
                continue;
            }

            $key = $tenant->getKey();

            if (! is_string($key)) {
                continue;
            }

            AutoPost2::dispatch($key, $article->id, 'create')->delay(20);

            $canPost = $this->getSettingPlatforms($article, $platforms);

            if (empty($canPost)) {
                continue;
            }

            foreach ($canPost as $platform => $scheduled_at) {
                if ($this->isPosting($platform, $article->id)) {
                    continue;
                }

                $scheduled_at = Carbon::parse($scheduled_at);

                ArticleAutoPosting::create([
                    'article_id' => $article->id,
                    'platform' => $platform,
                    'state' => ($scheduled_at->isPast()) ? AutoPostingState::waiting() : AutoPostingState::initialized(),
                    'scheduled_at' => $scheduled_at,
                ]);

                AutoPost::dispatch($key, $article->id, $platform)->delay(20);
            }
        }
    }

    /**
     * get the valid platform data
     *
     * @param  string[]  $platforms
     * @return array<string, string|null>
     */
    protected function getSettingPlatforms(Article $article, array $platforms): array
    {
        /** @var array{array{enable: bool}}|null */
        $autoPosting = $article->auto_posting;

        if ($autoPosting === null) {
            return [];
        }

        $result = [];

        foreach ($platforms as $platform) {
            if (! isset($autoPosting[$platform])) {
                continue;
            }

            /** @var bool $enable */
            $enable = Arr::get($autoPosting[$platform], 'enable', false);

            if (! $enable) {
                continue;
            }

            /** @var string|Carbon $scheduled_at */
            $scheduled_at = Arr::get($autoPosting[$platform], 'scheduled_at', now());

            $result[$platform] = $scheduled_at;
        }

        return $result;
    }

    /**
     * get active integration.
     *
     * @return string[]
     */
    protected function getActivatedIntegration(): array
    {
        /** @var string[] $keys */
        $keys = Integration::activated()
            ->whereNotNull('data')
            ->pluck('key')
            ->all();

        return $keys;
    }

    /**
     * check the auto post's state is schedule, posted or not
     */
    protected function isPosting(string $platform, int $id): bool
    {
        $query = ArticleAutoPosting::where('article_id', $id)
            ->where('platform', $platform)
            ->whereNotIn('state', [AutoPostingState::cancelled(), AutoPostingState::aborted()]);

        return $query->exists();
    }
}
