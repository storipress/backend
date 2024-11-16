<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations\Billing;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final readonly class CheckProphetRemaining
{
    /**
     * @param  array{}  $args
     */
    public function __invoke(null $_, array $args): int
    {
        $now = time();

        $end = Carbon::parse('2024-04-04 08:00:00', 'UTC')->getTimestamp();

        if ($now >= $end) {
            return 2;
        }

        $used = DB::table('subscriptions')
            ->where('stripe_status', '=', 'active')
            ->where('stripe_price', '=', 'prophet')
            ->count();

        $remaining = 50 - $used;

        $diff = intval(($end - $now) / 3600);

        if ($diff >= 24) {
            $adjust = 50;
        } else {
            $adjust = 2 + intval(log($diff, 1.06845048)); // day 1 remaining at most 2
        }

        return max(min($remaining, $adjust), 2);
    }
}
