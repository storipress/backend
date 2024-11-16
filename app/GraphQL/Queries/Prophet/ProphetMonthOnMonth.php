<?php

declare(strict_types=1);

namespace App\GraphQL\Queries\Prophet;

use App\Models\Tenants\ArticleAnalysis;
use Illuminate\Database\Eloquent\Collection;

final readonly class ProphetMonthOnMonth
{
    /**
     * @param  array{}  $args
     * @return Collection<int, ArticleAnalysis>.
     */
    public function __invoke(null $_, array $args): Collection
    {
        return ArticleAnalysis::query()->whereNull('date')
            ->whereNotNull('year')
            ->whereNotNull('month')
            ->latest('year')
            ->latest('month')
            ->take(6)
            ->get(['data', 'year', 'month']);
    }
}
