<?php

declare(strict_types=1);

namespace App\GraphQL\Queries\Prophet;

use App\Models\Tenants\ArticleAnalysis;
use Illuminate\Database\Eloquent\Collection;

final readonly class ProphetDashboardChart
{
    /**
     * @param  array{}  $args
     * @return Collection<int, ArticleAnalysis>
     */
    public function __invoke(null $_, array $args): Collection
    {
        return ArticleAnalysis::whereNotNull('date')
            ->oldest('date')
            ->get(['data', 'date']);
    }
}
