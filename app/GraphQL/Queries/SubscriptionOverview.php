<?php

namespace App\GraphQL\Queries;

use App\Models\Tenants\Analysis;
use Illuminate\Support\Collection;

class SubscriptionOverview
{
    /**
     * @param  array<string, mixed>  $args
     * @return array<string, Analysis|null>
     */
    public function __invoke($_, array $args): array
    {
        /** @var Collection<int, Analysis> $data */
        $data = Analysis::whereNull('date')
            ->whereNotNull('year')
            ->whereNotNull('month')
            ->latest('year')
            ->latest('month')
            ->take(2)
            ->get([
                'subscribers', 'paid_subscribers', 'active_subscribers',
                'revenue', 'email_sends', 'email_opens', 'email_clicks',
            ]);

        return [
            'current' => $data->first(),
            'previous' => $data->last(),
        ];
    }
}
