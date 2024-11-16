<?php

namespace App\Console\Migrations;

use App\Builder\ReleaseEventsBuilder;
use App\Models\User;
use Illuminate\Console\Command;

class MigrateActivateFreeTrial extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:activate-free-trial';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $now = now();

        $users = User::withoutEagerLoads()
            ->with(['publications'])
            ->whereNotIn('id', [
                1470, // Shopify Demo Account（PTHCIN328）
                2380, // Zapier Demo Account（POCHV8NEN）
            ])
            ->where('trial_ends_at', '>', $now)
            ->lazyById(50);

        foreach ($users as $user) {
            $user->update(['trial_ends_at' => $now]);

            foreach ($user->publications as $publication) {
                $publication->run(
                    fn () => (new ReleaseEventsBuilder())->handle('trial:ended'),
                );
            }
        }

        return static::SUCCESS;
    }
}
