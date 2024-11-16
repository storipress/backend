<?php

namespace App\Jobs\Scraper;

use App\Mail\UserScraperStartMail;
use App\Models\Tenant;
use App\Models\Tenants\Scraper;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Stancl\Tenancy\Exceptions\TenantCouldNotBeIdentifiedById;

use function Sentry\captureException;

class StartScraperRunner implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(
        protected int $id,
        protected string $token,
        protected string $type,
        protected string $tenant,
    ) {
        //
    }

    /**
     * Execute the job.
     *
     *
     * @throws TenantCouldNotBeIdentifiedById
     */
    public function handle(): void
    {
        $apiToken = config('services.apify.api_token');

        if (!is_string($apiToken)) {
            return;
        }

        $tenant = Tenant::find($this->tenant);

        if ($tenant === null) {
            return;
        }

        tenancy()->initialize($tenant);

        $scraper = Scraper::find($this->id);

        if ($scraper === null) {
            return;
        }

        $http = Http::connectTimeout(5)
            ->timeout(10)
            ->retry(3, 1000)
            ->asJson()
            ->withToken($apiToken)
            ->withUserAgent('storipress/2022-10-26');

        try {
            $http->post($this->endpoint(), [
                'token' => $this->token,
                'type' => $this->type,
                'clientId' => $this->tenant,
                'oid' => (string) $tenant->owner->id,
            ]);

            $scraper->update(['started_at' => now()]);

            Mail::to($tenant->owner->email)->send(
                new UserScraperStartMail(),
            );
        } catch (RequestException $e) {
            $error = $e->response->json('error.type');

            if ($error !== 'actor-memory-limit-exceeded') {
                captureException($e);
            } else {
                tenancy()->end();

                $this->release(60 * 5); // 5 minutes
            }
        }
    }

    /**
     * Get apify API endpoint.
     */
    protected function endpoint(): string
    {
        $runner = match (app()->environment()) {
            'production' => 'storipress~article-scrape-migrator-prod',
            'staging' => 'storipress~article-scrape-migrator-staging',
            default => 'storipress~article-scrape-migrator-dev',
        };

        return sprintf('https://api.apify.com/v2/acts/%s/runs', $runner);
    }
}
