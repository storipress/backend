<?php

namespace App\Listeners\Entity\Article\ArticlePublished;

use App\Enums\AutoPosting\State;
use App\Jobs\Integration\AutoPost;
use App\Jobs\Integration\AutoPost2;
use App\Models\Tenant;
use App\Models\Tenants\Article;
use App\Models\Tenants\ArticleAutoPosting;
use App\Models\Tenants\Integration;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

trait AutoPostHelper
{
    public function autoPost(Tenant $tenant, Article $article, int $delay = 0): void
    {
        $configuration = $article->auto_posting;

        if (empty($configuration)) {
            return;
        }

        /** @var string[] $platforms */
        $platforms = Integration::activated()
            ->whereIn('key', ['facebook', 'twitter'])
            ->whereNotNull('data')
            ->pluck('key')
            ->toArray();

        foreach ($platforms as $platform) {
            if (!isset($configuration[$platform])) {
                continue;
            }

            $enabled = Arr::get($configuration[$platform], 'enable', false);

            if ($enabled !== true) {
                continue;
            }

            $time = Arr::get($configuration[$platform], 'scheduled_at');

            if ($time !== null && !is_not_empty_string($time)) {
                continue;
            }

            $shared = ArticleAutoPosting::where('article_id', $article->id)
                ->where('platform', $platform)
                ->whereNotIn('state', [State::cancelled(), State::aborted()])
                ->exists();

            if ($shared) {
                continue;
            }

            $scheduledAt = Carbon::parse($time);

            ArticleAutoPosting::create([
                'article_id' => $article->id,
                'platform' => $platform,
                'state' => ($scheduledAt->isPast()) ? State::waiting() : State::initialized(),
                'scheduled_at' => $scheduledAt,
            ]);

            // auto-post v1
            AutoPost::dispatch($tenant->id, $article->id, $platform)->delay($delay);

            // auto-post v2
            // AutoPost2::dispatch($tenant->id, $article->id, 'create')->delay($delay);
        }
    }
}
