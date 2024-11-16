<?php

namespace App\GraphQL\Mutations\Article;

use App\Exceptions\NotFoundHttpException;
use App\GraphQL\Traits\HasGPTHelper;
use App\Models\Tenants\Article;
use App\Models\Tenants\UserActivity;
use Illuminate\Support\Str;

final class SummarizeArticleContent
{
    use HasGPTHelper;

    /**
     * @param  array{
     *     id: string,
     * }  $args
     */
    public function __invoke($_, array $args): string
    {
        $article = Article::find($args['id']);

        if ($article === null) {
            throw new NotFoundHttpException();
        }

        if (empty($article->plaintext) || Str::length($article->plaintext) < 200) {
            return 'Article content less than 200 characters is not possible to use this feature.';
        }

        UserActivity::log(
            name: 'article.content.summarize',
            subject: $article,
        );

        return $this->chat(
            Str::of('Summarize the following article with no more than 150 words:')
                ->newLine(2)
                ->append($article->plaintext)
                ->toString(),
        );
    }
}
