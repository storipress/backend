<?php

namespace App\Console\Migrations;

use App\Models\User;
use App\Sluggable;
use Illuminate\Console\Command;

class MigrateUserSlug extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:user-slug';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $users = User::withoutEagerLoads()
            ->whereNull('slug')
            ->orWhere('slug', 'LIKE', 'appsumo-%')
            ->lazyById(50);

        foreach ($users as $user) {
            if ($user->full_name === null) {
                continue;
            }

            $user->update([
                'slug' => Sluggable::slug($user->full_name),
            ]);
        }

        return static::SUCCESS;
    }
}
