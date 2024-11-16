<?php

namespace App\Console\Schedules\FifteenMinutes;

use App\Console\Schedules\Command;
use App\Enums\AutoPosting\State;
use App\Models\Tenant;
use App\Models\Tenants\Article;
use App\Models\Tenants\ArticleAutoPosting;
use App\Models\Tenants\Integration;
use App\Models\Tenants\ReleaseEvent;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\LazyCollection;
use Webmozart\Assert\Assert;

class DetectAbnormalAutoPosting extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $now = now()->toImmutable();

        $from = $now->startOfMinute()->subMinutes(30);

        $to = $from->endOfMinute()->addMinutes(15);

        runForTenants(function (Tenant $tenant) use ($from, $to) {
            $platforms = Integration::whereIn('key', ['facebook', 'twitter'])
                ->whereNotNull('internals')
                ->activated()
                ->pluck('key')
                ->toArray();

            if (empty($platforms)) {
                return;
            }

            Assert::allStringNotEmpty($platforms);

            /** @var LazyCollection<int, Article> $articles */
            $articles = Article::with('stage', 'autoPostings')
                ->whereBetween('published_at', [$from, $to])
                ->whereNotNull('auto_posting')
                ->select(['id', 'stage_id', 'auto_posting', 'published_at'])
                ->lazyById(50);

            if ($articles->isEmpty()) {
                return;
            }

            $expected = [];

            foreach ($articles as $article) {
                if (!$article->published) {
                    continue;
                }

                if (empty($article->auto_posting)) {
                    continue;
                }

                if (!Arr::hasAny($article->auto_posting, $platforms)) {
                    continue;
                }

                foreach ($platforms as $platform) {
                    $key = sprintf('%s.enable', $platform);

                    $enabled = Arr::get($article->auto_posting, $key, false);

                    if (!$enabled) {
                        continue;
                    }

                    $expected[$article->id][] = $platform;
                }
            }

            if (empty($expected)) {
                return;
            }

            /** @var Collection<int, ReleaseEvent> $events */
            $events = ReleaseEvent::with('release')
                ->whereBetween('updated_at', [$from, $to->addMinute()])
                ->whereIn('name', ['article:publish', 'article:schedule'])
                ->select(['id', 'data', 'release_id'])
                ->get();

            $built = [];

            foreach ($events as $event) {
                Assert::notNull($event->data);

                if ($event->release === null) {
                    continue;
                }

                $built = array_merge($built, Arr::flatten($event->data));
            }

            // make sure that all published articles have been properly built
            $inQueue = array_diff(
                array_keys($expected),
                array_unique($built),
            );

            if (!empty($inQueue)) {
                Log::channel('slack')->error(
                    '[Auto Post] The following articles are not built yet',
                    [
                        'tenant' => $tenant->id,
                        'articles' => $inQueue,
                    ],
                );
            }

            foreach ($expected as $id => $platforms) {
                $actual = ArticleAutoPosting::where('article_id', '=', $id)
                    ->where('state', '=', State::posted())
                    ->pluck('platform')
                    ->toArray();

                $missing = array_diff($platforms, $actual);

                if (empty($missing)) {
                    continue;
                }

                Log::channel('slack')->error(
                    '[Auto Post] Can not find the article auto posting',
                    [
                        'tenant' => $tenant->id,
                        'article' => $id,
                        'platforms' => $missing,
                    ],
                );
            }
        });

        return static::SUCCESS;
    }
}
