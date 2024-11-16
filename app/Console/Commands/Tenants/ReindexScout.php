<?php

namespace App\Console\Commands\Tenants;

use App\Jobs\Typesense\MakeSearchable;
use App\Models\Tenant;
use App\Models\Tenants\Article;
use App\Models\Tenants\Subscriber;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Typesense\Exceptions\ObjectAlreadyExists;
use Typesense\Exceptions\ObjectNotFound;

class ReindexScout extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'scout:reindex {tenant? : tenant id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reindex scout';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $tenantId = $this->argument('tenant');

        $key = sprintf('scout:reindex:%s', $tenantId ?: 'all');

        if (! Cache::add($key, now()->timestamp, 60)) {
            return static::FAILURE;
        }

        $tenants = Tenant::withTrashed()->initialized();

        if (is_not_empty_string($tenantId)) {
            $tenants->where('id', '=', $tenantId);
        }

        runForTenants(function (Tenant $tenant) {
            $this->info(
                sprintf('drop %s collections...', $tenant->id),
            );

            try {
                Subscriber::removeAllFromSearch();
            } catch (ObjectNotFound|ObjectAlreadyExists) {
                // ignored
            }

            try {
                Article::removeAllFromSearch();
            } catch (ObjectNotFound|ObjectAlreadyExists) {
                // ignored
            }

            if ($tenant->trashed()) {
                return;
            }

            $this->info(
                sprintf('sync %s collections...', $tenant->id),
            );

            Article::withoutEagerLoads()
                ->select(['id'])
                ->chunkById(50, function (Collection $articles) {
                    MakeSearchable::dispatchSync($articles);
                });

            Subscriber::withoutEagerLoads()
                ->where('id', '>', 0)
                ->select(['id'])
                ->chunkById(50, function (Collection $subscribers) {
                    MakeSearchable::dispatchSync($subscribers);
                });
        }, $tenants->lazyById(50));

        Cache::forget($key);

        return static::SUCCESS;
    }
}
