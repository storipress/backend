<?php

namespace App\Jobs\Revert;

use App\Models\Tenant;
use App\Models\Tenants\Subscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

class PullDealFromHubSpot implements ShouldQueue
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
            $subscriber = Subscriber::find($this->subscriberId);

            if (!($subscriber instanceof Subscriber)) {
                return;
            }

            if (!is_not_empty_string($subscriber->hubspot_id)) {
                return;
            }

            $customer = sprintf('%s-hubspot', $tenant->id);

            $revert = app('revert')
                ->setToken($token)
                ->setCustomerId($customer)
                ->deal();

            $options = [
                'pageSize' => '100',
            ];

            do {
                $deals = $revert->list($options);

                foreach ($deals['data'] as $deal) {
                    // @todo save
                }

                if ($deals['pagination']->next) {
                    $options['cursor'] = $deals['pagination']->next;
                }
            } while ($deals['pagination']->next);
        });
    }
}
