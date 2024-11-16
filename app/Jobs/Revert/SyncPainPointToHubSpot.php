<?php

namespace App\Jobs\Revert;

use App\Models\Tenant;
use App\Models\Tenants\Subscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

class SyncPainPointToHubSpot implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $tenantId,
        public int $subscriberId,
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $token = config('services.revert.token');

        if (!is_not_empty_string($token)) {
            return;
        }

        $tenant = Tenant::withoutEagerLoads()
            ->initialized()
            ->find($this->tenantId);

        if (!($tenant instanceof Tenant)) {
            return;
        }

        $tenant->run(function (Tenant $tenant) use ($token) {
            $subscriber = Subscriber::withoutEagerLoads()
                ->with(['pain_point'])
                ->find($this->subscriberId);

            if (!($subscriber instanceof Subscriber)) {
                return;
            }

            if (!is_not_empty_string($subscriber->hubspot_id)) {
                return;
            }

            $data = $subscriber->pain_point?->data;

            if (empty($data) || !is_array($data)) {
                return;
            }

            $insights = array_slice($data, 0, 5);

            $customer = sprintf('%s-hubspot', $tenant->id);

            app('revert')
                ->setToken($token)
                ->setCustomerId($customer)
                ->contact()
                ->update($subscriber->hubspot_id, [
                    'additional' => [
                        'sp_pain_points' => array_column($insights, 'goal'),
                    ],
                ]);
        });
    }
}
