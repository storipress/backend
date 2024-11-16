<?php

namespace App\GraphQL\Mutations\Upload;

use App\Exceptions\NotFoundHttpException;
use App\Models\Tenants\Article;
use Illuminate\Http\UploadedFile;

final class UploadArticleImage extends UploadMutation
{
    /**
     * @param  array<string, mixed>  $args
     */
    public function __invoke($_, array $args): string
    {
        /** @var Article|null $article */
        $article = Article::find($args['id']);

        if (is_null($article)) {
            throw new NotFoundHttpException();
        }

        /** @var UploadedFile $file */
        $file = $args['file'];

        $path = $this->upload($file);

        $article->images()->create($this->getImageAttributes($path, $file));

        return $path;
    }

    /**
     * {@inheritDoc}
     */
    protected function group(): string
    {
        return 'articles';
    }

    /**
     * {@inheritDoc}
     */
    protected function directory(): ?string
    {
        return now()->format('Y/m/d');
    }
}
