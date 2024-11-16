<?php

namespace App\Console\Schedules\TenMinutes;

use App\Console\Schedules\Command;
use App\Enums\AutoPosting\State;
use App\Models\Tenant;
use App\Models\Tenants\ArticleAutoPosting;
use Illuminate\Support\Facades\Log;

class DetectDuplicateAutoPosting extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $to = now()->toImmutable();

        $from = $to->startOfMinute()->subMinutes(15);

        runForTenants(function (Tenant $tenant) use ($from, $to) {
            $duplicates = ArticleAutoPosting::whereBetween('created_at', [$from, $to])
                ->whereNotIn('platform', ['slack'])
                ->whereNotIn('state', [State::cancelled(), State::aborted()])
                ->groupBy('article_id', 'platform')
                ->havingRaw('count(*) > 1')
                ->get(['article_id', 'platform'])
                ->toArray();

            if (empty($duplicates)) {
                return;
            }

            Log::channel('slack')->error(
                '[Auto Post] Detect duplicate auto posting',
                [
                    'client' => $tenant->id,
                    'data' => $duplicates,
                ],
            );
        });

        return static::SUCCESS;
    }
}
