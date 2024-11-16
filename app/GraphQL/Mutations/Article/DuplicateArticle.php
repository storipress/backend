<?php

namespace App\GraphQL\Mutations\Article;

use App\Events\Entity\Article\ArticleDuplicated;
use App\Exceptions\NotFoundHttpException;
use App\Models\Tenant;
use App\Models\Tenants\Article;
use App\Models\Tenants\Stage;
use App\Models\Tenants\User;
use App\Models\Tenants\UserActivity;
use Webmozart\Assert\Assert;

final class DuplicateArticle extends ArticleMutation
{
    /**
     * @param  array<string, mixed>  $args
     */
    public function __invoke($_, array $args): Article
    {
        $tenant = tenant();

        Assert::isInstanceOf($tenant, Tenant::class);

        /** @var Article|null $article */
        $article = Article::find($args['id']);

        if (is_null($article)) {
            throw new NotFoundHttpException();
        }

        $this->authorize('write', $article);

        $copy = [
            'desk_id',
            'layout_id',
            'title',
            'blurb',
            'document',
            'cover',
            'seo',
        ];

        $new = new Article(array_merge($article->only($copy), [
            'encryption_key' => base64_encode(random_bytes(32)),
        ]));

        $new->stage()->associate($this->stageId());

        $new->save();

        $user = User::find(auth()->user()?->getAuthIdentifier());

        $new->authors()->attach($user);

        $new->refresh();

        $new->searchable();

        ArticleDuplicated::dispatch($tenant->id, $new->id);

        UserActivity::log(
            name: 'article.duplicate',
            subject: $new,
            data: ['from' => $article->getKey()],
        );

        return $new;
    }

    /**
     * Get default stage id.
     */
    protected function stageId(): int
    {
        return Stage::withoutEagerLoads()->default()->sole(['id'])->id;
    }
}
