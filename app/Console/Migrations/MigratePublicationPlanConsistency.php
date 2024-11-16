<?php

namespace App\Console\Migrations;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MigratePublicationPlanConsistency extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:publication-plan-consistency';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $users = User::withoutEagerLoads()
            ->with(['publications', 'subscriptions'])
            ->has('publications')
            ->lazyById(50);

        foreach ($users as $user) {
            $plan = 'free';

            if (
                $user->subscribed() &&
                ($subscription = $user->subscription()) &&
                $subscription->stripe_price
            ) {
                $plan = Str::before($subscription->stripe_price, '-');
            }

            foreach ($user->publications as $publication) {
                $publication->update(['plan' => $plan]);
            }
        }

        return static::SUCCESS;
    }
}
