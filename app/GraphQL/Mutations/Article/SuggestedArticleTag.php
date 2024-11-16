<?php

namespace App\GraphQL\Mutations\Article;

use App\Exceptions\BadRequestHttpException;
use App\Exceptions\NotFoundHttpException;
use App\GraphQL\Traits\HasGPTHelper;
use App\Models\Tenants\Article;
use App\Models\Tenants\UserActivity;
use Illuminate\Support\Str;

final class SuggestedArticleTag
{
    use HasGPTHelper;

    /**
     * @param  array{
     *     id: string,
     * }  $args
     * @return array<int, string>
     */
    public function __invoke($_, array $args): array
    {
        $article = Article::find($args['id']);

        if ($article === null) {
            throw new NotFoundHttpException();
        }

        if (empty($article->plaintext) || Str::length($article->plaintext) < 200) {
            throw new BadRequestHttpException();
        }

        UserActivity::log(
            name: 'article.tags.suggested',
            subject: $article,
        );

        $tags = $this->chat(
            Str::of('Give me three tags for the following article and separate them with a comma:')
                ->newLine(2)
                ->append($article->plaintext)
                ->toString(),
        );

        return array_map('trim', explode(',', $tags));
    }
}
