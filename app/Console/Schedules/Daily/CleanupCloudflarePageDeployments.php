<?php

namespace App\Console\Schedules\Daily;

use App\Console\Schedules\Command;
use App\Models\CloudflarePage;
use App\Models\CloudflarePageDeployment;
use Illuminate\Contracts\Console\Isolatable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;
use Sentry\State\Scope;
use Webmozart\Assert\Assert;

use function Sentry\captureException;
use function Sentry\configureScope;

class CleanupCloudflarePageDeployments extends Command implements Isolatable
{
    /**
     * Max deployments number for each tenant.
     */
    public const MAX = 2;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        sleep(60);

        $cloudflare = app('cloudflare');

        $subQuery = CloudflarePageDeployment::withoutEagerLoads()
            ->groupBy('tenant_id')
            ->having(DB::raw('count(`tenant_id`)'), '>', static::MAX)
            ->select(['tenant_id']);

        $deployments = CloudflarePageDeployment::withoutEagerLoads()
            ->with('page')
            ->whereIn('tenant_id', $subQuery)
            ->latest('created_at')
            ->get(['id', 'cloudflare_page_id', 'tenant_id', 'created_at', 'deleted_at'])
            ->groupBy('tenant_id')
            ->map(fn (Collection $collection) => $collection->skip(static::MAX))
            ->flatten();

        Assert::allIsInstanceOf($deployments, CloudflarePageDeployment::class);

        foreach ($deployments as $idx => $deployment) {
            if (($idx % 2) === 0) {
                sleep(1);
            }

            try {
                configureScope(function (Scope $scope) use ($deployment) {
                    $scope->setContext('cf-page-deployment', $deployment->attributesToArray());
                });

                Assert::isInstanceOf($deployment->page, CloudflarePage::class);

                $deleted = $cloudflare->deletePageDeployment(
                    $deployment->page->name,
                    $deployment->id,
                    true,
                );

                Assert::true($deleted);

                $deployment->delete();
            } catch (ConnectionException) {
                sleep(10);
            } catch (RequestException $e) {
                if ($e->response->json('errors.0.code') === 8000009) { // The deployment ID you have specified does not exist. Update the deployment ID and try again.
                    $deployment->delete();
                } elseif (in_array($e->response->status(), [500, 502, 503, 504], true)) {
                    sleep(30);
                } else {
                    captureException($e);
                }
            }
        }

        return static::SUCCESS;
    }
}
