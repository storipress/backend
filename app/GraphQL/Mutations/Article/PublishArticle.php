<?php

namespace App\GraphQL\Mutations\Article;

use App\Enums\Article\PublishType;
use App\Events\Entity\Article\ArticlePublished;
use App\Exceptions\InternalServerErrorHttpException;
use App\Exceptions\NotFoundHttpException;
use App\Models\Tenant;
use App\Models\Tenants\Article;
use App\Models\Tenants\Stage;
use App\Models\Tenants\UserActivity;
use Illuminate\Support\Carbon;
use Segment\Segment;
use Webmozart\Assert\Assert;

final class PublishArticle extends ArticleMutation
{
    /**
     * @param  array<string, string>  $args
     */
    public function __invoke($_, array $args): Article
    {
        $article = Article::find($args['id']);

        if ($article === null) {
            throw new NotFoundHttpException();
        }

        $this->authorize('write', $article);

        if (($args['now'] ?? false) && $article->stage->name !== 'Reviewed') {
            $originStageId = $article->stage_id;

            $readyStageId = $this->readyStageId();

            $article->stage()->associate($readyStageId);

            if ($originStageId !== $readyStageId) {
                UserActivity::log(
                    name: 'article.stage.change',
                    subject: $article,
                    data: [
                        'old' => $originStageId,
                        'new' => $readyStageId,
                    ],
                );
            }
        }

        $useServerCurrentTime = ($args['useServerCurrentTime'] ?? false);

        if (! empty($args['time'] ?? '') || $useServerCurrentTime) {
            $time = $useServerCurrentTime ? now()->startOfSecond() : Carbon::parse($args['time']);

            $type = $time->isPast() ? PublishType::immediate() : PublishType::schedule();

            $updated = $article->update([
                'published_at' => $time,
                'publish_type' => $type,
            ]);

            if (! $updated) {
                throw new InternalServerErrorHttpException();
            }

            UserActivity::log(
                name: 'article.schedule',
                subject: $article,
                data: ['time' => $time],
            );

            Segment::track([
                'userId' => (string) auth()->id(),
                'event' => 'tenant_article_scheduled',
                'properties' => [
                    'tenant_uid' => tenant('id'),
                    'tenant_name' => tenant('name'),
                    'tenant_article_uid' => (string) $article->id,
                ],
                'context' => [
                    'groupId' => tenant('id'),
                ],
            ]);

            $tenant = tenant();

            Assert::isInstanceOf($tenant, Tenant::class);

            if (PublishType::immediate()->is($type)) {
                ArticlePublished::dispatch($tenant->id, $article->id);
            }
        } else {
            $article->save();
        }

        $article->refresh();

        return $article;
    }

    /**
     * Get ready stage id.
     */
    protected function readyStageId(): int
    {
        return Stage::withoutEagerLoads()->ready()->sole(['id'])->id;
    }
}
