<?php

namespace App\GraphQL\Mutations\Article;

use App\Events\Entity\Article\ArticleCreated;
use App\Exceptions\BadRequestHttpException;
use App\Models\Tenant;
use App\Models\Tenants\Article;
use App\Models\Tenants\Desk;
use App\Models\Tenants\Stage;
use App\Models\Tenants\UserActivity;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Segment\Segment;
use Webmozart\Assert\Assert;

class CreateArticle extends ArticleMutation
{
    /**
     * @param array{
     *     title?: string,
     *     blurb?: string,
     *     published_at?: string,
     *     desk_id: string,
     *     author_ids?: array<int, string>,
     * } $args
     */
    public function __invoke($_, array $args): Article
    {
        $tenant = tenant();

        Assert::isInstanceOf($tenant, Tenant::class);

        $desk = Desk::find($args['desk_id']);

        Assert::isInstanceOf($desk, Desk::class);

        $this->authorize('write', [Article::class, $desk]);

        // filter array arguments
        if (! Cache::add(hmac(Arr::except($args, ['author_ids'])), true, 1)) {
            Log::debug('Create a article with same arguments too quickly.', [
                'tenant' => $tenant->getKey(),
                'args' => $args,
            ]);

            throw new BadRequestHttpException();
        }

        $article = new Article(array_merge(
            [
                'title' => 'Untitled',
                'encryption_key' => base64_encode(random_bytes(32)),
            ],
            array_filter(Arr::only($args, ['title', 'blurb', 'published_at'])),
        ));

        $article->desk()->associate($desk);

        $article->stage()->associate($this->stageId());

        $article->save();

        $authors = collect([auth()->id()])
            ->push(...($args['author_ids'] ?? []))
            ->filter()
            ->map(fn ($id) => intval($id))
            ->unique()
            ->values();

        $article->authors()->sync($authors);

        $article->refresh();

        ArticleCreated::dispatch($tenant->id, $article->id);

        UserActivity::log(
            name: 'article.create',
            subject: $article,
            data: $args,
        );

        Segment::track([
            'userId' => (string) auth()->id(),
            'event' => 'tenant_article_created',
            'properties' => [
                'tenant_uid' => $tenant->id,
                'tenant_name' => $tenant->name,
                'tenant_article_uid' => (string) $article->id,
            ],
            'context' => [
                'groupId' => $tenant->id,
            ],
        ]);

        return $article;
    }

    /**
     * Get default stage id.
     */
    protected function stageId(): int
    {
        return Stage::withoutEagerLoads()->default()->sole(['id'])->id;
    }
}
