<?php

namespace App\GraphQL\Mutations\Article;

use App\Events\Entity\Tag\TagDeleted;
use App\Exceptions\NotFoundHttpException;
use App\Models\Tenant;
use App\Models\Tenants\Article;
use App\Models\Tenants\Tag;
use App\Models\Tenants\UserActivity;
use Webmozart\Assert\Assert;

final class RemoveTagFromArticle extends ArticleMutation
{
    /**
     * @param  array<string, mixed>  $args
     */
    public function __invoke($_, array $args): Article
    {
        $tenant = tenant();

        Assert::isInstanceOf($tenant, Tenant::class);

        $article = Article::find($args['id']);

        if (!($article instanceof Article)) {
            throw new NotFoundHttpException();
        }

        $this->authorize('write', $article);

        $article->tags()->detach($args['tag_id']);

        $article->refresh();

        $article->searchable();

        // cleanup tags that do not have any attached articles
        /** @var array<int> $ids */
        $ids = Tag::whereDoesntHave('articles')->pluck('id')->toArray();

        Tag::whereDoesntHave('articles')->delete();

        foreach ($ids as $id) {
            TagDeleted::dispatch($tenant->id, $id);
        }

        UserActivity::log(
            name: 'article.tags.remove',
            subject: $article,
            data: [
                'tag' => $args['tag_id'],
            ],
        );

        return $article;
    }
}
