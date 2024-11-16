<?php

namespace App\Console\Migrations;

use App\Models\Tenants\Tag;
use Illuminate\Console\Command;

class MigrateUnusedTags extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:unused-tags';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        runForTenants(fn () => Tag::whereDoesntHave('articles')->delete());

        return static::SUCCESS;
    }
}
