<?php

declare(strict_types=1);

namespace App\GraphQL\Queries\Prophet;

use App\Models\Tenants\ArticleAnalysis;
use Illuminate\Database\Eloquent\Collection;

final readonly class ProphetArticleStatistics
{
    /**
     * @param  array{
     *     sort_by: 'none'|'scroll_depth'|'reads'|'emails_collected'|'email_submit',
     *     desc: bool,
     * }  $args
     * @return Collection<int, ArticleAnalysis>
     */
    public function __invoke(null $_, array $args): Collection
    {
        $sort = [
            'none' => 'article_id',
            'scroll_depth' => 'data.avg_scrolled',
            'reads' => 'data.viewed',
            'emails_collected' => 'data.email_collected',
            'email_submit' => 'data.email_collected_ratio',
        ][$args['sort_by']];

        return ArticleAnalysis::query()
            ->withoutEagerLoads()
            ->with(['article'])
            ->whereNotNull('article_id')
            ->get()
            ->sortBy($sort, descending: $args['desc']);
    }
}
