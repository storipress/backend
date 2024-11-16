<?php

namespace App\GraphQL\Queries;

use App\Models\Tenants\Analysis;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class SubscriptionGraphs
{
    /**
     * @param  array<string, mixed>  $args
     * @return array<string, Collection<int, Analysis>>
     */
    public function __invoke($_, array $args): array
    {
        $subscribers = Analysis::whereNotNull('date')
            ->oldest('date')
            ->get(['subscribers', 'paid_subscribers', 'date']);

        $revenue = Analysis::whereNull('date')
            ->oldest('year')
            ->oldest('month')
            ->get(['revenue', 'year', 'month'])
            ->each(function (Analysis $item) {
                $item->date = Carbon::parse(
                    sprintf('%d-%d-01', $item->year, $item->month),
                );
            });

        return [
            'subscribers' => $subscribers,
            'revenue' => $revenue,
        ];
    }
}
