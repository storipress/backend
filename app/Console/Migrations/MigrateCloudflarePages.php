<?php

namespace App\Console\Migrations;

use App\Console\Commands\Domain\PushConfigToContentDeliveryNetwork;
use App\Console\Schedules\Monthly\ExpandCloudflarePages;
use App\Models\CloudflarePage;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class MigrateCloudflarePages extends Command
{
    /**
     * {@inheritdoc}
     */
    protected $signature = 'migrate:cf-pages';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $tenants = Tenant::withoutEagerLoads()
            ->whereNull('cloudflare_page_id')
            ->pluck('id')
            ->toArray();

        $pages = CloudflarePage::withoutEagerLoads()
            ->where('occupiers', '<', CloudflarePage::MAX)
            ->get(['id', 'occupiers', 'created_at', 'updated_at']);

        $remains = $pages->sum('remains');

        if (count($tenants) > $remains) {
            $expand = (new ExpandCloudflarePages())->getName();

            $this->error('There are not enough pages to assign the tenants.');

            $this->error(sprintf('Run "%s --force" command first.', $expand));

            return static::FAILURE;
        }

        $offset = 0;

        foreach ($pages as $page) {
            $take = $page->remains;

            $ids = array_slice($tenants, $offset, $take);

            $taken = count($ids);

            if ($taken === 0) {
                break;
            }

            Tenant::whereIn('id', $ids)->update([
                'cloudflare_page_id' => $page->id,
            ]);

            $page->increment('occupiers', $taken);

            Artisan::queue(PushConfigToContentDeliveryNetwork::class, [
                '--tenants' => $ids,
            ]);

            if ($take !== $taken) {
                break;
            }

            $offset += $taken;
        }

        return static::SUCCESS;
    }
}
