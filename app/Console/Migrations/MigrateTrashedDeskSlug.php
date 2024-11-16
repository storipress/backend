<?php

namespace App\Console\Migrations;

use App\Models\Tenants\Desk;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MigrateTrashedDeskSlug extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:trashed-desk-slug';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        runForTenants(function () {
            $desks = Desk::withoutEagerLoads()
                ->onlyTrashed()
                ->lazyById();

            foreach ($desks as $desk) {
                if ($desk->deleted_at === null) {
                    continue;
                }

                if (preg_match('/-\d{10}$/i', $desk->slug) === 1) {
                    continue;
                }

                $desk->updateQuietly([
                    'slug' => sprintf(
                        '%s-%d',
                        Str::limit($desk->slug, 240, ''),
                        $desk->deleted_at->timestamp,
                    ),
                ]);
            }
        });

        return static::SUCCESS;
    }
}
