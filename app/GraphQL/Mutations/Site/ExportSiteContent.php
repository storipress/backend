<?php

namespace App\GraphQL\Mutations\Site;

use App\GraphQL\Mutations\Mutation;
use App\Models\Tenants\Article;
use App\Models\Tenants\Desk;
use App\Models\Tenants\Tag;
use App\Models\Tenants\UserActivity;

final class ExportSiteContent extends Mutation
{
    /**
     * @param  array<string, mixed>  $args
     * @return array<mixed>
     */
    public function __invoke($_, array $args): array
    {
        $tags = Tag::all([
            'id', 'name', 'slug', 'description',
            'created_at', 'updated_at',
        ]);

        $desks = Desk::all([
            'id', 'name', 'slug', 'seo',
            'created_at', 'updated_at',
        ]);

        $articles = Article::with('tags:id')->get([
            'id', 'desk_id', 'stage_id', 'title', 'slug', 'blurb',
            'featured', 'document', 'html', 'cover', 'seo',
            'published_at', 'created_at', 'updated_at',
        ]);

        UserActivity::log(
            name: 'publication.export',
        );

        return [
            'tags' => $tags->toArray(),
            'desks' => $desks->toArray(),
            'articles' => $articles->toArray(),
        ];
    }
}
