<?php

namespace App\Console\Schedules\Daily;

use App\Console\Schedules\Command;
use App\Models\CloudflarePage;
use App\Models\CloudflarePageDeployment;
use Illuminate\Contracts\Console\Isolatable;
use Illuminate\Support\Carbon;
use Webmozart\Assert\Assert;

class SyncCloudflarePageDeployments extends Command implements Isolatable
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $cloudflare = app('cloudflare');

        $records = collect();

        foreach (CloudflarePage::lazyById(50) as $page) {
            $scannedAt = CloudflarePageDeployment::withTrashed()
                ->where('cloudflare_page_id', '=', $page->id)
                ->latest('created_at')
                ->value('created_at');

            Assert::nullOrIsInstanceOf($scannedAt, Carbon::class);

            $cursor = 1;

            while (true) {
                $deployments = $cloudflare->getPageDeployments($page->name, $cursor);

                if (empty($deployments)) {
                    break;
                }

                foreach ($deployments as $deployment) {
                    if ($scannedAt?->gte($deployment['created_on'])) {
                        break 2;
                    }

                    $records->add([
                        'id' => $deployment['id'],
                        'cloudflare_page_id' => $page->id,
                        'tenant_id' => $deployment['deployment_trigger']['metadata']['branch'],
                        'raw' => json_encode($deployment),
                        'created_at' => $deployment['created_on'],
                        'updated_at' => $deployment['modified_on'],
                    ]);
                }

                ++$cursor;
            }
        }

        if ($records->isEmpty()) {
            return static::SUCCESS;
        }

        $chunks = $records->sortBy('created_at')->chunk(50);

        foreach ($chunks as $chunk) {
            CloudflarePageDeployment::insertOrIgnore($chunk->toArray());
        }

        return static::SUCCESS;
    }
}
