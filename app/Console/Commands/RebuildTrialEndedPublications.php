<?php

namespace App\Console\Commands;

use App\Builder\ReleaseEventsBuilder;
use App\Models\User;
use Illuminate\Console\Command;

class RebuildTrialEndedPublications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'site:rebuild:trial-ended';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $from = now()->startOfDay()->subDay()->toImmutable();

        $to = $from->endOfDay();

        $users = User::with('publications')
            ->whereBetween('trial_ends_at', [$from, $to])
            ->lazyById();

        foreach ($users as $user) {
            foreach ($user->publications as $publication) {
                $publication->run(
                    fn () => (new ReleaseEventsBuilder())->handle('trial:ended'),
                );
            }
        }
    }
}
