<?php

namespace App\GraphQL\Mutations\Article;

use App\AutoPosting\Dispatcher;
use App\Enums\AutoPosting\State;
use App\Exceptions\ErrorCode;
use App\Exceptions\HttpException;
use App\Jobs\Integration\AutoPost;
use App\Models\Tenant;
use App\Models\Tenants\Article;
use App\Models\Tenants\ArticleAutoPosting;
use App\Models\Tenants\Integration;
use App\Models\Tenants\UserActivity;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Webmozart\Assert\Assert;

class TriggerArticleSocialSharing extends ArticleMutation
{
    /**
     * @param  array{id: string}  $args
     */
    public function __invoke($_, array $args): bool
    {
        $tenant = tenant();

        if (! ($tenant instanceof Tenant)) {
            throw new HttpException(ErrorCode::NOT_FOUND);
        }

        $article = Article::find($args['id']);

        if (! ($article instanceof Article)) {
            throw new HttpException(ErrorCode::ARTICLE_NOT_FOUND);
        }

        if (! $article->published) {
            throw new HttpException(ErrorCode::ARTICLE_NOT_PUBLISHED);
        }

        $this->authorize('write', $article);

        $platforms = Integration::activated()
            ->whereIn('key', ['facebook', 'twitter'])
            ->whereNotNull('data')
            ->pluck('key')
            ->toArray();

        if (empty($platforms)) {
            throw new HttpException(ErrorCode::ARTICLE_SOCIAL_SHARING_INACTIVATED_INTEGRATIONS);
        }

        Assert::allStringNotEmpty($platforms);

        $configuration = $article->auto_posting;

        if (empty($configuration)) {
            throw new HttpException(ErrorCode::ARTICLE_SOCIAL_SHARING_MISSING_CONFIGURATION);
        }

        foreach ($platforms as $platform) {
            if (! isset($configuration[$platform])) {
                continue;
            }

            $enabled = Arr::get($configuration[$platform], 'enable', false);

            if ($enabled !== true) {
                continue;
            }

            $time = Arr::get($configuration[$platform], 'scheduled_at');

            Assert::nullOrStringNotEmpty($time);

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
            AutoPost::dispatch($tenant->id, $article->id, $platform);

            // auto-post v2
            $dispatcher = new Dispatcher($tenant, $article, 'create', []);

            $dispatcher->only(['linkedin']);

            $dispatcher->handle();
        }

        UserActivity::log(
            name: 'article.social-sharing.trigger',
            subject: $article,
        );

        return true;
    }
}
