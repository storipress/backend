<?php

namespace App\GraphQL\Mutations\Article;

use App\Events\Entity\Article\ArticleUpdated;
use App\Exceptions\InternalServerErrorHttpException;
use App\Exceptions\NotFoundHttpException;
use App\Models\Tenant;
use App\Models\Tenants\Article;
use App\Models\Tenants\UserActivity;
use Illuminate\Support\Arr;
use Webmozart\Assert\Assert;

class UpdateArticle extends ArticleMutation
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

        $attributes = Arr::except($args, ['id']);

        if (array_key_exists('slug', $attributes)) {
            $article->pathnames = ($article->pathnames ?: []) + [time() => sprintf('/posts/%s', $article->slug)];

            if (empty(trim($attributes['slug'] ?: ''))) {
                unset($attributes['slug']);

                $article->slug = '';
            }
        }

        $origin = $article->only(array_keys($attributes));

        if (Arr::has($attributes, 'cover')) {
            $originUrl = data_get($origin['cover'], 'url');

            $url = data_get($attributes['cover'], 'url');

            $mediaId = data_get($origin['cover'], 'wordpress_id');

            // If the image has not changed, retain the media id.
            if ($url === $originUrl && $mediaId) {
                data_set($attributes, 'cover.wordpress_id', $mediaId);
            }
        }

        if (Arr::has($attributes, 'seo')) {
            $originUrl = data_get($origin['seo'], 'ogImage');

            $url = data_get($attributes['seo'], 'ogImage');

            $mediaId = data_get($origin['seo'], 'ogImage_wordpress_id');

            // If the image has not changed, retain the media id.
            if ($url === $originUrl && $mediaId) {
                data_set($attributes, 'seo.ogImage_wordpress_id', $mediaId);
            }
        }

        $updated = $article->update($attributes);

        if (!$updated) {
            throw new InternalServerErrorHttpException();
        }

        $metaKeys = ['title', 'slug', 'blurb', 'featured'];

        if (Arr::hasAny($attributes, $metaKeys)) {
            UserActivity::log(
                name: 'article.meta.update',
                subject: $article,
                data: [
                    'old' => Arr::only($origin, $metaKeys),
                    'new' => Arr::only($attributes, $metaKeys),
                ],
            );
        }

        if (Arr::has($attributes, 'seo')) {
            UserActivity::log(
                name: 'article.seo.update',
                subject: $article,
                data: [
                    'old' => $origin['seo'],
                    'new' => $attributes['seo'],
                ],
            );
        }

        if (Arr::has($attributes, 'cover')) {
            UserActivity::log(
                name: 'article.cover.update',
                subject: $article,
                data: [
                    'old' => $origin['cover'],
                    'new' => $attributes['cover'],
                ],
            );
        }

        UserActivity::log(
            name: 'article.content.update',
            subject: $article,
            data: [
                'old' => $origin,
                'new' => $attributes,
            ],
        );

        ArticleUpdated::dispatch(
            $tenant->id,
            $article->id,
            array_keys($attributes),
        );

        return $article;
    }
}
