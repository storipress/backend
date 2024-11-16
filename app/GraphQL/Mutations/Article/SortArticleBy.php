<?php

namespace App\GraphQL\Mutations\Article;

use App\Enums\Article\SortBy;
use App\Models\Tenants\Article;
use App\Models\Tenants\UserActivity;
use Illuminate\Support\Facades\DB;
use stdClass;

class SortArticleBy
{
    /**
     * @param  array<string, string>  $args
     */
    public function __invoke($_, array $args): bool
    {
        /** @var SortBy $sortBy */
        $sortBy = $args['sort_by'];

        /** @var array<int, stdClass> $articles */
        $articles = DB::table('articles')
            ->where('stage_id', $args['stage_id'])
            ->whereNull('deleted_at')
            ->orderBy(...$this->getOrderBy($sortBy))
            ->get(['id']);

        foreach ($articles as $idx => $article) {
            DB::table('articles')
                ->where('id', $article->id)
                ->update(['order' => $idx + 1]);
        }

        Article::whereStageId($args['stage_id'])->searchable();

        UserActivity::log(
            name: 'article.sort',
            data: $args,
        );

        return true;
    }

    /**
     * @return array<int, string>
     */
    protected function getOrderBy(SortBy $sort_by): array
    {
        if ($sort_by->is(SortBy::dateCreated())) {
            return ['created_at', 'asc'];
        } elseif ($sort_by->is(SortBy::dateCreatedDesc())) {
            return ['created_at', 'desc'];
        } elseif ($sort_by->is(SortBy::articleName())) {
            return ['title', 'asc'];
        } elseif ($sort_by->is(SortBy::articleNameDesc())) {
            return ['title', 'desc'];
        } else {
            return ['created_at', 'asc'];
        }
    }
}
