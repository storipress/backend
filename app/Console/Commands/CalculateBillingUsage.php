<?php

namespace App\Console\Commands;

use App\Models\User;
use Generator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use RuntimeException;
use stdClass;

class CalculateBillingUsage extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'billing:usage:calculate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculate billing usage';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $now = now();

        if ($now->isSameDay(now()->firstOfMonth())) {
            $now->subMonthNoOverflow();
        }

        $range = [$now->copy()->firstOfMonth(), $now->copy()->endOfMonth()];

        foreach ($this->users() as $user) {
            if (! $user->subscribed()) {
                continue;
            }

            if ($user->onTrial()) {
                continue;
            }

            $subscription = $user->subscription();

            if (is_null($subscription)) {
                continue;
            }

            if ($subscription->name === 'appsumo') {
                continue;
            }

            $publications = $user->publications->pluck('id');

            $activities = 0;

            /** @var stdClass|null $usage */
            $usage = DB::table('subscription_usages')
                ->where('subscription_id', $subscription->getKey())
                ->where('current')
                ->first();

            if ($usage === null) {
                throw new RuntimeException(
                    'Missing subscription usages record.',
                );
            }

            if (intval($usage->usage) === $activities) {
                continue;
            }

            $subscription->reportUsage($activities);

            DB::table('subscription_usages')
                ->where('id', $usage->id)
                ->update(['usage' => $activities]);
        }

        return 0;
    }

    /**
     * @return Generator<User>
     */
    protected function users(): Generator
    {
        /** @var LazyCollection<int, User> $users */
        $users = User::has('publications')
            ->with('publications')
            ->lazy(25);

        foreach ($users as $user) {
            yield $user;
        }
    }
}
