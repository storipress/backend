<?php

namespace App\Jobs\Tenants\Database;

use App\Models\Tenants\Integration;
use App\Observers\TriggerSiteRebuildObserver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Stancl\Tenancy\Contracts\TenantWithDatabase;

final class CreateDefaultIntegrations implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    protected TenantWithDatabase $tenant;

    /**
     * Create a new job instance.
     */
    public function __construct(TenantWithDatabase $tenant)
    {
        $this->tenant = $tenant;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if (app()->runningUnitTests()) {
            return;
        }

        $this->tenant->run(function () {
            TriggerSiteRebuildObserver::mute();

            Integration::insert([
                [
                    'key' => 'code-injection',
                    'data' => json_encode(['header' => null, 'footer' => null]),
                ],
                [
                    'key' => 'google-analytics',
                    'data' => json_encode(['tracking_id' => null, 'anonymous' => true]),
                ],
                [
                    'key' => 'google-adsense',
                    'data' => json_encode([
                        'code' => null,
                        'ads.txt' => null,
                        'scopes' => [
                            'articles' => false,
                            'front-page' => false,
                        ],
                    ]),
                ],
                [
                    'key' => 'mailchimp',
                    'data' => json_encode(['action' => null]),
                ],
                [
                    'key' => 'disqus',
                    'data' => json_encode(['shortname' => null]),
                ],
                [
                    'key' => 'shopify',
                    'data' => json_encode([]),
                ],
                [
                    'key' => 'webflow',
                    'data' => json_encode([]),
                ],
                [
                    'key' => 'wordpress',
                    'data' => json_encode([]),
                ],
                [
                    'key' => 'zapier',
                    'data' => json_encode([]),
                ],
                [
                    'key' => 'linkedin',
                    'data' => json_encode([]),
                ],
                [
                    'key' => 'hubspot',
                    'data' => json_encode([]),
                ],
            ]);

            TriggerSiteRebuildObserver::unmute();
        });
    }
}
