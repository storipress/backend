<?php

namespace App\GraphQL\Queries;

use App\Enums\Credit\State;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Webmozart\Assert\Assert;

class CreditsOverview
{
    /**
     * @param  array{}  $args
     * @return array<int, mixed>
     */
    public function __invoke($_, array $args): array
    {
        /** @var User $user */
        $user = auth()->user();

        Assert::isInstanceOf($user, User::class);

        $map = [
            'invitation' => '500',
        ];

        return $user->credits()
            ->where('state', '=', State::available())
            ->get()
            ->groupBy('earned_from')
            ->map(function (Collection $credit, $key) use ($map) {
                return [
                    'type' => $key,
                    'amount' => $map[$key] ?? $credit->max('amount'),
                    'count' => $credit->count(),
                    'total' => $credit->sum('amount'),
                ];
            })
            ->values()
            ->toArray();
    }
}
