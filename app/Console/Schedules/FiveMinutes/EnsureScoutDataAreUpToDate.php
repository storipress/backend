<?php

namespace App\Console\Schedules\FiveMinutes;

use App\Console\Schedules\Command;
use App\Models\Tenants\Article;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class EnsureScoutDataAreUpToDate extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $primary = 'id';

        $from = now()->startOfMinute()->subMinutes(6);

        runForTenants(function () use ($primary, $from) {
            Article::withoutTrashed()
                ->withoutEagerLoads()
                ->where(function (Builder $query) use ($from) {
                    $query->where('updated_at', '>=', $from)
                        ->orWhere('published_at', '>=', $from);
                })
                ->orderBy($primary)
                ->select([$primary])
                ->chunk(
                    50,
                    fn (Collection $articles) => $articles->searchable(),
                );

            Article::onlyTrashed()
                ->withoutEagerLoads()
                ->where('deleted_at', '>=', $from)
                ->orderBy($primary)
                ->select([$primary])
                ->chunk(
                    50,
                    fn (Collection $articles) => $articles->unsearchable(),
                );
        });

        return static::SUCCESS;
    }
}
