<?php

namespace App\GraphQL\Mutations\Site;

use App\GraphQL\Mutations\Mutation;
use App\Models\Tenants\Article;
use App\Models\Tenants\Desk;
use App\Models\Tenants\Tag;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

final class ImportSiteContent extends Mutation
{
    /**
     * @param  array<string, UploadedFile>  $args
     */
    public function __invoke($_, array $args): bool
    {
        /** @var UploadedFile $file */
        $file = $args['file'];

        /** @var array<string, array<mixed>> $data */
        $data = json_decode($file->getContent(), true);

        if (! $data) {
            return false;
        }

        $tags = [];

        if (isset($data['tags'])) {
            $tags = $this->insertTags($data['tags']);
        }

        $desks = [];

        if (isset($data['desks'])) {
            $desks = $this->insertDesks($data['desks']);
        }

        if (isset($data['articles'])) {
            $this->insertArticles($data['articles'], $desks, $tags);
        }

        return true;
    }

    /**
     * Insert tags data.
     *
     * @param  array<mixed>  $data
     * @return array<int>
     */
    protected function insertTags(array $data): array
    {
        $tags = [];

        foreach ($data as $datum) {
            $tags[$datum['id']] = Tag::firstOrCreate(
                ['name' => $datum['name']],
                Arr::only($datum, [
                    'slug', 'description', 'created_at', 'updated_at',
                ]),
            )->getKey();
        }

        return $tags;
    }

    /**
     * Insert desks data.
     *
     * @param  array<mixed>  $data
     * @return array<int>
     */
    protected function insertDesks(array $data): array
    {
        $desks = [];

        foreach ($data as $datum) {
            $attributes = Arr::only($datum, [
                'slug', 'seo', 'created_at', 'updated_at',
            ]);

            if (Desk::withTrashed()->whereSlug($datum['slug'])->exists()) {
                $attributes['slug'] .= '-'.Str::random(4);
            }

            $desks[$datum['id']] = Desk::firstOrCreate(
                ['name' => $datum['name']],
                $attributes,
            )->getKey();
        }

        return $desks;
    }

    /**
     * Insert articles data.
     *
     * @param  array<mixed>  $data
     * @param  array<int>  $desks
     * @param  array<int>  $tags
     */
    protected function insertArticles(array $data, array $desks, array $tags): void
    {
        foreach ($data as $datum) {
            if (! isset($desks[$datum['desk_id']])) {
                continue;
            }

            $attributes = Arr::only($datum, [
                'stage_id', 'title', 'slug', 'blurb', 'featured',
                'document', 'cover', 'seo',
                'published_at', 'created_at', 'updated_at',
            ]);

            if (Article::withTrashed()->whereSlug($datum['slug'])->exists()) {
                $attributes['slug'] .= '-'.Str::random(4);
            }

            $attributes['desk_id'] = $desks[$datum['desk_id']];

            /** @var Article $article */
            $article = Article::create($attributes);

            $article->tags()->sync(array_map(function ($id) use ($tags) {
                return $tags[$id];
            }, array_column($datum['tags'], 'id')));
        }

        // cleanup tags without attached articles
        Tag::whereDoesntHave('articles')->delete();
    }
}
