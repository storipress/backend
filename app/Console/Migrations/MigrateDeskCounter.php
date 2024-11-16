<?php

namespace App\Console\Migrations;

use App\Models\Tenant;
use App\Models\Tenants\Desk;
use App\Models\Tenants\Stage;
use Illuminate\Console\Command;
use Webmozart\Assert\Assert;

class MigrateDeskCounter extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:desk-counter {tenant? : tenant id}';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $tenantId = $this->argument('tenant');

        if (is_not_empty_string($tenantId)) {
            $tenant = Tenant::where('id', '=', $tenantId)->sole();
        }

        runForTenants(function () {
            $readyId = Stage::ready()->value('id');

            Assert::integer($readyId);

            $sub = Desk::withoutEagerLoads()
                ->whereNotNull('desk_id')
                ->lazyById();

            foreach ($sub as $desk) {
                $this->own($desk, $readyId);
            }

            $standalone = Desk::withoutEagerLoads()
                ->root()
                ->whereDoesntHave('desks')
                ->lazyById();

            foreach ($standalone as $desk) {
                $this->own($desk, $readyId);
            }

            $root = Desk::withoutEagerLoads()
                ->root()
                ->whereHas('desks')
                ->lazyById();

            foreach ($root as $desk) {
                $this->sum($desk);
            }
        }, isset($tenant) ? [$tenant] : null);

        return static::SUCCESS;
    }

    protected function sum(Desk $desk): void
    {
        $desk->load('desks');

        $desks = $desk->desks;

        $desk->update([
            'draft_articles_count' => $desks->sum('draft_articles_count'),
            'published_articles_count' => $desks->sum('published_articles_count'),
            'total_articles_count' => $desks->sum('total_articles_count'),
        ]);
    }

    protected function own(Desk $desk, int $readyId): void
    {
        $total = $desk
            ->articles()
            ->count();

        $published = $desk
            ->articles()
            ->where('stage_id', '=', $readyId)
            ->where('published_at', '<=', now())
            ->count();

        $desk->update([
            'draft_articles_count' => $total - $published,
            'published_articles_count' => $published,
            'total_articles_count' => $total,
        ]);
    }
}
