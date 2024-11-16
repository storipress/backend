<?php

namespace App\Console\Schedules\Daily;

use App\Console\Schedules\Command;
use App\Events\Entity\Domain\CustomDomainRemoved;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Webmozart\Assert\Assert;

class CleanupOccupiedCustomDomains extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $tenants = Tenant::onlyTrashed()
            ->withoutEagerLoads()
            ->where(function (Builder $query) {
                $query
                    ->whereNotNull('custom_domain')
                    ->orWhereHas('custom_domains');
            })
            ->pluck('id')
            ->toArray();

        Assert::allStringNotEmpty($tenants);

        foreach ($tenants as $tenant) {
            CustomDomainRemoved::dispatch($tenant);
        }

        return static::SUCCESS;
    }
}
